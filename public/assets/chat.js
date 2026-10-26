(() => {
    const { socketUrl, token, room, user } = window.CHAT;
    const list = document.getElementById('chat-list');
    const form = document.getElementById('chat-form');
    const textInput = document.getElementById('text');
    const fileInput = document.getElementById('file');
    const preview = document.getElementById('preview');
    const previewImg = document.getElementById('preview-img');
    let pendingImage = null;

    // textContent のみ使用し XSS を避ける
    const el = (tag, cls, text) => {
        const n = document.createElement(tag);
        if (cls) n.className = cls;
        if (text != null) n.textContent = text;
        return n;
    };

    const formatDate = (iso) => {
        const d = new Date(iso);
        return isNaN(d) ? '' : d.toLocaleString('ja-JP', { month: 'numeric', day: 'numeric', hour: '2-digit', minute: '2-digit' });
    };

    const senderEl = (m) => {
        const w = el('div', 'text-gray-500 py-1');
        w.append(el('span', 'p-1 text-sm', m.sender || ''), el('span', 'text-xs text-gray-400', m.date ? formatDate(m.date) : ''));
        return w;
    };

    const toolBtn = (label, title) => {
        const b = el('button', 'px-2 py-1 text-xs text-white border rounded bg-gray-400 disabled:opacity-50', label);
        b.type = 'button';
        b.title = title;
        return b;
    };

    // 読み上げ / 翻訳
    const speak = (m) => {
        if (!m.lang) return;
        const u = new SpeechSynthesisUtterance(m.text);
        u.lang = m.lang;
        const voice = speechSynthesis.getVoices().find(v => v.lang === u.lang);
        if (voice) u.voice = voice;
        speechSynthesis.cancel();
        speechSynthesis.speak(u);
    };

    const translate = async (m, btn, out) => {
        if (!m.text || !m.lang || user.lang === m.lang) return;
        btn.disabled = true;
        try {
            const res = await fetch((window.CHAT.base || '') + '/translate.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ text: m.text, fromLang: m.lang, toLang: user.lang }),
            });
            if (res.ok) {
                out.textContent = (await res.json()).translated || '';
                btn.remove();
            }
        } finally {
            btn.disabled = false;
        }
    };

    // メッセージ描画
    const textEl = (m) => {
        const own = m.userId == user.id;
        const wrap = el('div');
        wrap.append(senderEl(m));
        const bubble = el('div', `max-w-xs px-4 py-2 rounded-lg ${own ? 'bg-sky-500 text-white' : 'bg-gray-100 text-gray-600'}`);
        bubble.append(document.createTextNode(m.text));
        if (!own && m.lang) {
            const tools = el('div', 'my-2');
            const out = el('p', 'py-2 text-gray-600 font-bold');
            const speakBtn = toolBtn('🔊', '読み上げ');
            const trBtn = toolBtn('翻訳', '翻訳');
            speakBtn.onclick = () => speak(m);
            trBtn.onclick = () => translate(m, trBtn, out);
            tools.append(speakBtn, el('span', 'ml-2'), trBtn, out);
            bubble.append(tools);
        }
        wrap.append(bubble);
        return wrap;
    };

    const imageEl = (m) => {
        const wrap = el('div');
        wrap.append(senderEl(m));
        const img = el('img', 'rounded-lg mb-2 max-w-[200px]');
        img.src = URL.createObjectURL(new Blob([m.buffer]));
        wrap.append(img);
        return wrap;
    };

    const append = (m) => {
        const row = el('div', 'flex mb-3');
        if (m.buffer) row.append(imageEl(m));
        if (m.text) row.append(textEl(m));
        list.append(row);
        window.scrollTo({ top: document.documentElement.scrollHeight, behavior: 'smooth' });
    };

    // Socket
    const socket = io(socketUrl, { auth: { token } });
    socket.on('connect', () => socket.emit('join-room', { room }));
    socket.on('connect_error', (e) => console.error('socket error:', e.message));
    socket.on('message', append);
    socket.on('image', append);
    socket.on('user-joined', append);

    // 送信
    const clearImage = () => {
        if (previewImg.src) URL.revokeObjectURL(previewImg.src);
        previewImg.removeAttribute('src');
        preview.classList.add('hidden');
        pendingImage = null;
        fileInput.value = '';
    };

    form.addEventListener('submit', (ev) => {
        ev.preventDefault();
        const text = textInput.value.trim();
        if (text) {
            const msg = { text, room, userId: user.id, sender: user.name, token, lang: user.lang || '' };
            socket.emit('message', msg);
            append(msg);
            textInput.value = '';
        }
        if (pendingImage) {
            const file = pendingImage;
            clearImage();
            file.arrayBuffer().then(buffer =>
                socket.emit('image', { buffer, room, userId: user.id, sender: user.name, token }));
        }
    });

    fileInput.addEventListener('change', () => {
        const file = fileInput.files[0];
        if (!file) return;
        pendingImage = file;
        previewImg.src = URL.createObjectURL(file);
        preview.classList.remove('hidden');
    });
    document.getElementById('preview-cancel').addEventListener('click', clearImage);

    // 音声入力
    const SR = window.SpeechRecognition || window.webkitSpeechRecognition;
    const mic = document.getElementById('mic');
    if (!SR) {
        mic.disabled = true;
    } else {
        const rec = new SR();
        rec.lang = user.lang || 'ja-JP';
        rec.interimResults = false;
        let listening = false;
        rec.onresult = (ev) => { textInput.value += ev.results[0][0].transcript; };
        rec.onend = () => { listening = false; mic.classList.remove('bg-red-100', 'border-red-400'); };
        mic.addEventListener('click', () => {
            if (listening) return rec.stop();
            listening = true;
            mic.classList.add('bg-red-100', 'border-red-400');
            rec.start();
        });
    }
})();
