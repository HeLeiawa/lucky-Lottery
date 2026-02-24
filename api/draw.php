<?php
session_start();
header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 0);
try {
    if (!isset($_SESSION['user_id'])) {
        echo json_encode(['code' => 0, 'msg' => '请先登录']);
        exit;
    }
    $config = require __DIR__ . '/../config/database.php';
    $pdo = new PDO("mysql:host={$config['host']};dbname={$config['dbname']};charset=utf8mb4", $config['user'], $config['pass']);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $user_id = intval($_SESSION['user_id']);
    $user = $pdo->query("SELECT * FROM users WHERE id=$user_id")->fetch(PDO::FETCH_ASSOC);
    if (!$user || $user['status'] != 1) {
        echo json_encode(['code' => 0, 'msg' => '账号已被封禁']);
        exit;
    }
    $pool_id = isset($_POST['pool_id']) ? intval($_POST['pool_id']) : 0;
    $draw_type = isset($_POST['draw_type']) ? trim($_POST['draw_type']) : 'code';
    $code = isset($_POST['code']) ? strtoupper(trim($_POST['code'])) : '';
    if ($pool_id <= 0) {
        echo json_encode(['code' => 0, 'msg' => '请选择正确的奖池']);
        exit;
    }
    $pool = $pdo->query("SELECT * FROM pools WHERE id=$pool_id AND is_active=1")->fetch(PDO::FETCH_ASSOC);
    if (!$pool) {
        echo json_encode(['code' => 0, 'msg' => '奖池不存在或已关闭']);
        exit;
    }
    $code_id = null;
    $new_balance = $user['balance'];
    $is_card_as_code = false;
    $direct_win_prize = null;
    if ($draw_type === 'code') {
        if (empty($code)) {
            echo json_encode(['code' => 0, 'msg' => '请输入兑换码']);
            exit;
        }
        $stmt = $pdo->prepare("SELECT * FROM codes WHERE UPPER(code)=? AND is_used=0 LIMIT 1");
        $stmt->execute([$code]);
        $code_info = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($code_info) {
            if ($code_info['pool_id'] != 0 && $code_info['pool_id'] != $pool_id) {
                echo json_encode(['code' => 0, 'msg' => '该兑换码不属于当前奖池']);
                exit;
            }
            $code_id = $code_info['id'];
        } else {
            $stmt = $pdo->prepare("SELECT * FROM card_codes WHERE UPPER(code)=? AND is_used=0 LIMIT 1");
            $stmt->execute([$code]);
            $card_info = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$card_info) {
                echo json_encode(['code' => 0, 'msg' => '兑换码不存在、已使用或不属于该奖池']);
                exit;
            }
            $stmt = $pdo->prepare("SELECT * FROM prizes WHERE id=? AND pool_id=? AND stock>0");
            $stmt->execute([$card_info['prize_id'], $pool_id]);
            $prize = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$prize) {
                echo json_encode(['code' => 0, 'msg' => '该卡密对应的奖品不属于当前奖池或已售罄']);
                exit;
            }
            $is_card_as_code = true;
            $direct_win_prize = $prize;
            $code_id = $card_info['id'];
        }
    } else {
        if ($pool['draw_price'] <= 0) {
            echo json_encode(['code' => 0, 'msg' => '该奖池不支持余额抽奖']);
            exit;
        }
        if ($user['balance'] < $pool['draw_price']) {
            echo json_encode(['code' => 0, 'msg' => '余额不足，请先充值']);
            exit;
        }
    }

    if ($draw_type === 'code') {
        if ($is_card_as_code) {
            $pdo->exec("UPDATE card_codes SET is_used=1, used_at=NOW(), winner_id=$user_id WHERE id=$code_id");
        } else {
            $pdo->exec("UPDATE codes SET is_used=1, used_time=NOW() WHERE id=$code_id");
        }
    }

    $pdo->beginTransaction();
    try {
        if ($draw_type === 'balance') {
            $balance_before = $user['balance'];
            $new_balance = $balance_before - $pool['draw_price'];
            $stmt = $pdo->prepare("UPDATE users SET balance=? WHERE id=?");
            $stmt->execute([$new_balance, $user_id]);
            $stmt = $pdo->prepare("INSERT INTO balance_logs (user_id, type, amount, balance_before, balance_after, remark) VALUES (?, 2, ?, ?, ?, ?)");
            $stmt->execute([$user_id, $pool['draw_price'], $balance_before, $new_balance, '抽奖消费 - ' . $pool['name']]);
        }

        $is_win = false;
        $win_prize = null;
        $card_code = null;
        $card_code_id = null;
        $is_guarantee_trigger = false;
        if ($is_card_as_code && $direct_win_prize) {
            $is_win = true;
            $win_prize = $direct_win_prize;
        } else {
            $prizes = $pdo->query("SELECT * FROM prizes WHERE pool_id=$pool_id AND stock>0")->fetchAll(PDO::FETCH_ASSOC);
            if (empty($prizes)) {
                $pdo->rollBack();
                echo json_encode(['code' => 0, 'msg' => '该奖池暂无可用奖品']);
                exit;
            }
            if ($pool['guarantee_count'] > 0) {
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM user_draw_logs WHERE user_id=? AND pool_id=? AND is_win=0");
                $stmt->execute([$user_id, $pool_id]);
                $lose_count = $stmt->fetchColumn();
                if ($lose_count >= $pool['guarantee_count']) {
                    $is_guarantee_trigger = true;
                }
            }
            if ($is_guarantee_trigger) {
                $guarantee_prizes = array_filter($prizes, function($p) {
                    return $p['is_guarantee'] == 1;
                });
                if (!empty($guarantee_prizes)) {
                    $total_guarantee_prob = array_sum(array_column($guarantee_prizes, 'guarantee_probability'));
                    if ($total_guarantee_prob > 0) {
                        $rand = mt_rand(1, 10000);
                        $current_prob = 0;
                        foreach ($guarantee_prizes as $p) {
                            $current_prob += round($p['guarantee_probability'] * 100);
                            if ($rand <= $current_prob) {
                                $win_prize = $p;
                                $is_win = true;
                                break;
                            }
                        }
                    }
                }
            }
            if (!$win_prize) {
                $total_normal_prob = array_sum(array_column($prizes, 'probability'));
                if ($total_normal_prob > 0) {
                    $rand = mt_rand(1, 10000);
                    $current_prob = 0;
                    foreach ($prizes as $p) {
                        $current_prob += round($p['probability'] * 100);
                        if ($rand <= $current_prob) {
                            $win_prize = $p;
                            $is_win = true;
                            break;
                        }
                    }
                }
            }
        }

        $stmt = $pdo->prepare("INSERT INTO user_draw_logs (user_id, pool_id, is_win) VALUES (?, ?, ?)");
        $stmt->execute([$user_id, $pool_id, $is_win ? 1 : 0]);
        if (!$is_win || !$win_prize) {
            $pdo->commit();
            $msg = $is_guarantee_trigger ? '保底触发但未中保底奖品，谢谢参与！' : '谢谢参与，下次好运！';
            $response = ['code' => 0, 'msg' => $msg];
            if ($draw_type === 'balance') {
                $response['new_balance'] = $new_balance;
            }
            echo json_encode($response);
            exit;
        }

        if ($win_prize['is_card']) {
            if ($is_card_as_code) {
                $card_code = $code;
                $card_code_id = $code_id;
            } else {
                $stmt = $pdo->prepare("SELECT * FROM card_codes WHERE prize_id=? AND is_used=0 ORDER BY id ASC LIMIT 1");
                $stmt->execute([$win_prize['id']]);
                $card = $stmt->fetch(PDO::FETCH_ASSOC);
                if (!$card) {
                    $pdo->rollBack();
                    echo json_encode(['code' => 0, 'msg' => '该奖品卡密已耗尽，请联系管理员']);
                    exit;
                }
                $card_code = $card['code'];
                $card_code_id = $card['id'];
            }
        }

        $pdo->exec("UPDATE prizes SET stock=stock-1 WHERE id={$win_prize['id']}");
        $stmt = $pdo->prepare("INSERT INTO winners (user_id, code_id, prize_id, draw_type, card_code_id, win_time) VALUES (?, ?, ?, ?, ?, NOW())");
        $stmt->execute([
            $user_id,
            $code_id,
            $win_prize['id'],
            $draw_type === 'code' ? 1 : 2,
            $card_code_id
        ]);
        $winner_id = $pdo->lastInsertId();

        if ($card_code_id && !$is_card_as_code) {
            $stmt = $pdo->prepare("UPDATE card_codes SET is_used=1, winner_id=?, used_at=NOW() WHERE id=?");
            $stmt->execute([$winner_id, $card_code_id]);
            $card_count = $pdo->query("SELECT COUNT(*) FROM card_codes WHERE prize_id={$win_prize['id']} AND is_used=0")->fetchColumn();
            $pdo->exec("UPDATE prizes SET stock=$card_count WHERE id={$win_prize['id']}");
        }

        $pdo->exec("DELETE FROM user_draw_logs WHERE user_id=$user_id AND pool_id=$pool_id");
        $pdo->commit();
        $msg = $is_card_as_code ? '🎉 使用卡密直接中奖！' : '🎉 恭喜中奖！';
        $response = [
            'code' => 1,
            'msg' => $msg,
            'prize' => [
                'name' => $win_prize['name'],
                'image' => $win_prize['image'] ? '../' . $win_prize['image'] : ''
            ]
        ];
        if ($card_code) {
            $response['card_code'] = $card_code;
            $response['is_card'] = true;
        }
        if ($draw_type === 'balance') {
            $response['new_balance'] = $new_balance;
        }
        echo json_encode($response);
    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['code' => 0, 'msg' => '抽奖失败：' . $e->getMessage()]);
    }
} catch (Exception $e) {
    echo json_encode(['code' => 0, 'msg' => '系统错误：' . $e->getMessage()]);
}
?>
