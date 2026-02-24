function draw() {
    const code = document.getElementById('code').value;
    if (!code) return alert('请输入兑换码');

    fetch('api/draw.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'code=' + encodeURIComponent(code)
    })
    .then(res => res.json())
    .then(data => {
        const div = document.getElementById('result');
        if (data.code === 1) {
            let html = `<h3 style="color:green;">${data.msg}</h3>`;
            html += `<p>奖品：${data.prize.name}</p>`;
            if (data.prize.image) html += `<img src="${data.prize.image}">`;
            div.innerHTML = html;
        } else {
            div.innerHTML = `<p style="color:red;">${data.msg}</p>`;
        }
    });
}