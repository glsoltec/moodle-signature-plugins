define([], function() {

    var W = 340, H = 68;

    function clean(text) {
        return (text || '').replace(/[^A-Za-z\u00C0-\u00D6\u00D8-\u00F6\u00F8-\u00FF\s\-\.]/g, '').substring(0, 60).trim() || ' ';
    }

    function drawCanvas(fonts, slug, text) {
        var info = fonts[slug];
        var canvas = document.getElementById('canvas-' + slug);
        if (!canvas) {
            return;
        }
        var ctx = canvas.getContext('2d');
        ctx.clearRect(0, 0, W, H);

        var safe = clean(text) || ' ';
        var fs = parseInt(info.size, 10);
        ctx.font = fs + 'px ' + info.family;
        while (ctx.measureText(safe).width > W - 24 && fs > 22) {
            fs -= 2;
            ctx.font = fs + 'px ' + info.family;
        }
        ctx.fillStyle = info.color;
        ctx.textBaseline = 'middle';
        ctx.textAlign = 'center';
        ctx.fillText(safe, W / 2, H / 2);
    }

    function drawAll(fonts, text) {
        Object.keys(fonts).forEach(function(s) {
            drawCanvas(fonts, s, text);
        });
    }

    function buildPng(fonts, currentFont, currentText) {
        var info = fonts[currentFont];
        var hd = document.createElement('canvas');
        hd.width = W * 2;
        hd.height = H * 2;
        var ctx = hd.getContext('2d');
        ctx.scale(2, 2);

        var safe = clean(currentText);
        var fs = parseInt(info.size, 10);
        ctx.font = fs + 'px ' + info.family;
        while (ctx.measureText(safe).width > W - 24 && fs > 22) {
            fs -= 2;
            ctx.font = fs + 'px ' + info.family;
        }
        ctx.fillStyle = info.color;
        ctx.textBaseline = 'middle';
        ctx.textAlign = 'center';
        ctx.fillText(safe, W / 2, H / 2);
        return hd.toDataURL('image/png');
    }

    function loadFonts(fonts, callback) {
        if (document.fonts && document.fonts.load) {
            var promises = Object.keys(fonts).map(function(s) {
                return document.fonts.load('40px ' + fonts[s].family);
            });
            Promise.all(promises).then(callback).catch(callback);
        } else {
            setTimeout(callback, 800);
        }
    }

    return {
        init: function(fonts, selectedFont, defaultText) {
            var currentFont = selectedFont;
            var currentText = defaultText;
            var timer;

            drawAll(fonts, currentText);

            document.querySelectorAll('.sig-card').forEach(function(card) {
                card.addEventListener('click', function() {
                    currentFont = this.dataset.font;
                    document.querySelectorAll('.sig-card').forEach(function(c) {
                        c.classList.remove('selected');
                    });
                    this.classList.add('selected');
                    document.getElementById('sig-selectedfont').value = currentFont;
                });
            });

            var textInput = document.getElementById('sig-text-input');
            if (textInput) {
                textInput.addEventListener('input', function() {
                    currentText = this.value;
                    clearTimeout(timer);
                    timer = setTimeout(function() {
                        drawAll(fonts, currentText);
                    }, 100);
                });
            }

            var form = document.getElementById('sig-form');
            if (form) {
                form.addEventListener('submit', function() {
                    var png = buildPng(fonts, currentFont, currentText);
                    document.getElementById('sig-imagedata').value = png;
                    document.getElementById('sig-selectedfont').value = currentFont;
                });
            }

            loadFonts(fonts, function() {
                drawAll(fonts, currentText);
            });
        }
    };
});
