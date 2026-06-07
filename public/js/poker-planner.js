(function () {
    var room = document.getElementById('poker-room');
    if (room) {
        var url = room.dataset.heartbeatUrl;
        var interval = Number(room.dataset.heartbeatMs || 30000);
        if (url && interval > 0) {
            window.setInterval(function () {
                fetch(url, { method: 'POST', credentials: 'same-origin' }).catch(function () {});
            }, interval);
        }
    }

    function copyShareLink(button) {
        var shareRoom = button.closest('#poker-room');
        if (!(shareRoom instanceof HTMLElement)) {
            return;
        }

        var shareUrl = shareRoom.dataset.shareUrl;
        if (!shareUrl) {
            return;
        }

        var label = button.querySelector('.pp-share-btn__label');
        var status = shareRoom.querySelector('#pp-share-status');

        function indicateCopied() {
            button.classList.add('is-copied');
            if (label) {
                label.textContent = 'Copied!';
            }
            if (status) {
                status.textContent = 'Room link copied to clipboard.';
            }

            window.setTimeout(function () {
                button.classList.remove('is-copied');
                if (label) {
                    label.textContent = 'Share link';
                }
                if (status) {
                    status.textContent = '';
                }
            }, 2000);
        }

        if (navigator.clipboard && typeof navigator.clipboard.writeText === 'function') {
            navigator.clipboard.writeText(shareUrl).then(indicateCopied).catch(function () {
                fallbackCopy(shareUrl, indicateCopied);
            });
            return;
        }

        fallbackCopy(shareUrl, indicateCopied);
    }

    function fallbackCopy(text, onSuccess) {
        var input = document.createElement('textarea');
        input.value = text;
        input.setAttribute('readonly', '');
        input.style.position = 'fixed';
        input.style.left = '-9999px';
        document.body.appendChild(input);
        input.select();

        try {
            if (document.execCommand('copy')) {
                onSuccess();
            }
        } finally {
            document.body.removeChild(input);
        }
    }

    document.addEventListener('click', function (event) {
        var shareButton = event.target.closest('.pp-share-btn');
        if (shareButton) {
            event.preventDefault();
            copyShareLink(shareButton);
        }
    });

    function setPicked(button, picked) {
        if (picked) {
            button.classList.add('is-picked');
            button.setAttribute('aria-pressed', 'true');
            return;
        }

        button.classList.remove('is-picked');
        button.removeAttribute('aria-pressed');
    }

    function clearPickedCards() {
        document.querySelectorAll('.pp-deck__card.is-picked').forEach(function (el) {
            setPicked(el, false);
        });
    }

    function applyTurboStream(html) {
        if (window.Turbo && typeof window.Turbo.renderStreamMessage === 'function') {
            window.Turbo.renderStreamMessage(html);
        }
    }

    document.addEventListener('click', function (event) {
        var button = event.target.closest('.pp-deck__card');
        if (!button || button.disabled || button.closest('fieldset[disabled]')) {
            return;
        }

        var form = button.closest('#vote-form');
        if (!(form instanceof HTMLFormElement)) {
            return;
        }

        if (button.classList.contains('is-picked')) {
            event.preventDefault();
            event.stopPropagation();

            var clearUrl = form.dataset.voteClearUrl;
            if (!clearUrl) {
                setPicked(button, false);
                return;
            }

            fetch(clearUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    Accept: 'text/vnd.turbo-stream.html',
                },
            })
                .then(function (response) {
                    if (!response.ok) {
                        throw new Error('clear vote failed');
                    }

                    return response.text();
                })
                .then(function (html) {
                    setPicked(button, false);
                    applyTurboStream(html);
                })
                .catch(function () {
                    setPicked(button, true);
                });

            return;
        }

        clearPickedCards();
        setPicked(button, true);
    }, true);

    document.addEventListener('turbo:submit-end', function (event) {
        if (!event.detail.success) {
            return;
        }

        var form = event.target;
        if (!(form instanceof HTMLFormElement) || form.id !== 'vote-form') {
            return;
        }

        form.querySelectorAll('button[type="submit"]').forEach(function (submitButton) {
            submitButton.disabled = false;
            submitButton.removeAttribute('aria-disabled');
        });
    });
})();
