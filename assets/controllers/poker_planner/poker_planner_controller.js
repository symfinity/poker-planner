import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    connect() {
        this.onClick = this.onClick.bind(this);
        this.onChange = this.onChange.bind(this);
        this.onDialogBackdropClick = this.onDialogBackdropClick.bind(this);
        this.onDeckClick = this.onDeckClick.bind(this);
        this.onTurboSubmitEnd = this.onTurboSubmitEnd.bind(this);
        this.syncStoryInputFromTitle = this.syncStoryInputFromTitle.bind(this);
        this.onTurboBeforeStreamRender = this.onTurboBeforeStreamRender.bind(this);
        this.onTurboRender = this.onTurboRender.bind(this);
        this.restoreQueueDrawer = this.restoreQueueDrawer.bind(this);
        this.queueDrawerWasOpen = false;
        this.previousSlotVotes = null;
        this.ownSlotAnimatedAt = 0;

        this.element.addEventListener('click', this.onClick);
        this.element.addEventListener('change', this.onChange);
        this.element.addEventListener('click', this.onDialogBackdropClick);
        this.element.addEventListener('click', this.onDeckClick, true);
        document.addEventListener('turbo:submit-end', this.onTurboSubmitEnd);
        document.addEventListener('turbo:render', this.syncStoryInputFromTitle);
        document.addEventListener('turbo:frame-render', this.syncStoryInputFromTitle);
        document.addEventListener('turbo:render', this.restoreQueueDrawer);
        document.addEventListener('turbo:before-stream-render', this.onTurboBeforeStreamRender);
        document.addEventListener('turbo:render', this.onTurboRender);

        this.initRoomGoneBanner();
        this.initHeartbeat();
        this.initEntrySplash();
    }

    disconnect() {
        this.element.removeEventListener('click', this.onClick);
        this.element.removeEventListener('change', this.onChange);
        this.element.removeEventListener('click', this.onDialogBackdropClick);
        this.element.removeEventListener('click', this.onDeckClick, true);
        document.removeEventListener('turbo:submit-end', this.onTurboSubmitEnd);
        document.removeEventListener('turbo:render', this.syncStoryInputFromTitle);
        document.removeEventListener('turbo:frame-render', this.syncStoryInputFromTitle);
        document.removeEventListener('turbo:render', this.restoreQueueDrawer);
        document.removeEventListener('turbo:before-stream-render', this.onTurboBeforeStreamRender);
        document.removeEventListener('turbo:render', this.onTurboRender);

        if (this.heartbeatTimer) {
            window.clearInterval(this.heartbeatTimer);
        }
    }

    initRoomGoneBanner() {
        const banner = document.getElementById('pp-room-gone-banner');
        if (!(banner instanceof HTMLElement)) {
            return;
        }

        try {
            if (sessionStorage.getItem('pp-room-gone') === '1') {
                sessionStorage.removeItem('pp-room-gone');
                banner.textContent = banner.dataset.message || '';
                banner.hidden = false;
            }
        } catch (e) {}
    }

    initHeartbeat() {
        const room = document.getElementById('poker-room');
        if (!(room instanceof HTMLElement)) {
            return;
        }

        const url = room.dataset.heartbeatUrl;
        const interval = Number(room.dataset.heartbeatMs || 30000);
        if (!url || interval <= 0) {
            return;
        }

        this.heartbeatTimer = window.setInterval(() => {
            fetch(url, { method: 'POST', credentials: 'same-origin' })
                .then((response) => {
                    if (response.status === 410) {
                        const entryUrl = room.dataset.entryUrl || '/';
                        try {
                            sessionStorage.setItem('pp-room-gone', '1');
                        } catch (e) {}
                        window.location.assign(entryUrl);
                    }
                })
                .catch(() => {});
        }, interval);
    }

    initEntrySplash() {
        const splash = document.querySelector('.pp-entry--splash');
        if (!splash) {
            return;
        }

        const actions = splash.querySelector('.pp-entry__actions--delayed');
        if (actions instanceof HTMLElement) {
            window.setTimeout(() => {
                actions.classList.add('is-visible');
            }, 3000);
        }

        const startDialog = document.getElementById('pp-start-dialog');
        if (!(startDialog instanceof HTMLDialogElement)) {
            return;
        }

        if (startDialog.dataset.defaultTab) {
            this.activateEntryTab(startDialog.dataset.defaultTab);
        }

        if (startDialog.dataset.autoOpen === '1') {
            this.openDialog('pp-start-dialog');
        } else if (startDialog.querySelector('.pp-flash')) {
            this.openDialog('pp-start-dialog');
        }
    }

    onClick(event) {
        const openTrigger = event.target.closest('[data-dialog-open]');
        if (openTrigger) {
            event.preventDefault();
            this.openDialog(openTrigger.getAttribute('data-dialog-open'));
            return;
        }

        const closeTrigger = event.target.closest('[data-dialog-close]');
        if (closeTrigger) {
            event.preventDefault();
            this.closeDialog(closeTrigger.closest('dialog'));
            return;
        }

        const inviteCopy = event.target.closest('[data-invite-copy]');
        if (inviteCopy) {
            event.preventDefault();
            const inviteDialog = document.getElementById('pp-invite-dialog');
            const field = inviteDialog ? inviteDialog.querySelector('#pp-invite-url-field') : null;
            const status = inviteDialog ? inviteDialog.querySelector('#pp-invite-copy-status') : null;
            if (!(field instanceof HTMLInputElement)) {
                return;
            }

            this.copyText(field.value, () => {
                if (status) {
                    status.textContent = 'Link copied to clipboard.';
                }
            });
            return;
        }

        const recapButton = event.target.closest('[data-recap-copy]');
        if (recapButton) {
            event.preventDefault();
            const markdown = document.getElementById('recap-markdown');
            const status = document.getElementById('recap-copy-status');
            if (!(markdown instanceof HTMLTextAreaElement)) {
                return;
            }

            this.copyText('| Story | Estimate |\n| --- | --- |\n' + markdown.value, () => {
                if (status) {
                    status.textContent = 'Recap markdown copied to clipboard.';
                }
            });
            return;
        }

        const tabButton = event.target.closest('[data-settings-tab], [data-entry-tab]');
        if (tabButton) {
            event.preventDefault();
            if (tabButton.hasAttribute('data-settings-tab')) {
                this.activateSettingsTab(tabButton.getAttribute('data-settings-tab'));
            } else {
                this.activateEntryTab(tabButton.getAttribute('data-entry-tab'));
            }
        }
    }

    onChange(event) {
        const input = event.target;
        if (!(input instanceof HTMLInputElement)) {
            return;
        }

        if (input.type === 'radio' && input.name === 'rounding_mode') {
            const group = input.closest('.pp-scheme-segment');
            if (!group) {
                return;
            }

            group.querySelectorAll('.pp-scheme-segment__btn').forEach((button) => {
                const radio = button.querySelector('input[type="radio"]');
                button.classList.toggle('is-active', radio instanceof HTMLInputElement && radio.checked);
            });

            return;
        }

        if (input.type !== 'checkbox' || !input.closest('.pp-option-card')) {
            return;
        }

        const option = input.closest('.pp-option-card');
        if (!(option instanceof HTMLElement)) {
            return;
        }

        const face = option.querySelector('.pp-card-face--selectable');
        if (face instanceof HTMLElement) {
            face.classList.toggle('is-selected', input.checked);
        }
    }

    onDialogBackdropClick(event) {
        if (event.target instanceof HTMLDialogElement) {
            const rect = event.target.getBoundingClientRect();
            const inDialog =
                rect.top <= event.clientY &&
                event.clientY <= rect.top + rect.height &&
                rect.left <= event.clientX &&
                event.clientX <= rect.left + rect.width;
            if (!inDialog) {
                this.closeDialog(event.target);
            }
        }
    }

    onDeckClick(event) {
        const button = event.target.closest('.pp-deck__card');
        if (!button || button.disabled || button.closest('fieldset[disabled]')) {
            return;
        }

        const form = button.closest('#vote-form');
        if (!(form instanceof HTMLFormElement)) {
            return;
        }

        if (button.classList.contains('is-picked')) {
            event.preventDefault();
            event.stopPropagation();

            const clearUrl = form.dataset.voteClearUrl;
            if (!clearUrl) {
                this.setPicked(button, false);
                return;
            }

            button.classList.add('is-dropping');
            button.classList.remove('is-picked');
            this.animateSlotVote(false);

            window.setTimeout(() => {
                fetch(clearUrl, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        Accept: 'text/vnd.turbo-stream.html',
                    },
                })
                    .then((response) => {
                        if (!response.ok) {
                            throw new Error('clear vote failed');
                        }

                        return response.text();
                    })
                    .then((html) => {
                        this.applyTurboStream(html);
                    })
                    .catch(() => {
                        button.classList.remove('is-dropping');
                        this.setPicked(button, true);
                        this.animateSlotVote(true);
                    });
            }, 280);

            return;
        }

        this.clearPickedCards();
        this.setPicked(button, true);
        this.animateSlotVote(true);
    }

    onTurboSubmitEnd(event) {
        if (!event.detail.success) {
            return;
        }

        const form = event.target;
        if (!(form instanceof HTMLFormElement)) {
            return;
        }

        if (form.id === 'vote-form') {
            form.querySelectorAll('button[type="submit"]').forEach((submitButton) => {
                submitButton.disabled = false;
                submitButton.removeAttribute('aria-disabled');
            });
            return;
        }

        if (form.id === 'story-queue-form') {
            const input = document.getElementById('story-title-input');
            if (input instanceof HTMLInputElement && document.activeElement === input) {
                input.focus();
            }
        }
    }

    onTurboBeforeStreamRender(event) {
        const drawer = document.querySelector('[data-queue-drawer]');
        if (drawer instanceof HTMLElement) {
            this.queueDrawerWasOpen = drawer.classList.contains('is-open');
        }

        const detail = event.detail;
        if (!detail || !detail.newStream) {
            return;
        }

        const html = detail.newStream.innerHTML || '';
        if (html.indexOf('target="slot-grid"') !== -1 || html.indexOf("target='slot-grid'") !== -1) {
            this.previousSlotVotes = this.snapshotSlotVotes();
        }

        if (html.indexOf('data-celebrate-confetti="1"') !== -1) {
            window.setTimeout(() => {
                this.syncRoomMetaFromStream();
                this.fireConfetti();
            }, 60);
        }
    }

    onTurboRender() {
        this.applySlotVoteAnimations();
    }

    snapshotSlotVotes() {
        const grid = document.getElementById('slot-grid');
        if (!(grid instanceof HTMLElement)) {
            return null;
        }

        /** @type {Record<string, boolean>} */
        const votes = {};
        grid.querySelectorAll('.pp-slot[data-participant-id]').forEach((slot) => {
            if (!(slot instanceof HTMLElement)) {
                return;
            }

            const id = slot.dataset.participantId;
            if (!id) {
                return;
            }

            votes[id] = slot.classList.contains('pp-slot--voted');
        });

        return votes;
    }

    applySlotVoteAnimations() {
        if (!this.previousSlotVotes) {
            return;
        }

        const grid = document.getElementById('slot-grid');
        if (!(grid instanceof HTMLElement)) {
            this.previousSlotVotes = null;
            return;
        }

        grid.querySelectorAll('.pp-slot[data-participant-id]').forEach((slot) => {
            if (!(slot instanceof HTMLElement)) {
                return;
            }

            const id = slot.dataset.participantId;
            if (!id || !(id in this.previousSlotVotes)) {
                return;
            }

            const voted = slot.classList.contains('pp-slot--voted');
            const wasVoted = this.previousSlotVotes[id];
            if (voted === wasVoted) {
                return;
            }

            const room = document.getElementById('poker-room');
            const selfId = room instanceof HTMLElement ? room.dataset.selfParticipantId : '';
            if (id === selfId && Date.now() - this.ownSlotAnimatedAt < 500) {
                return;
            }

            this.playSlotVoteAnimation(slot, voted);
        });

        this.previousSlotVotes = null;
    }

    animateSlotVote(voted) {
        const room = document.getElementById('poker-room');
        if (!(room instanceof HTMLElement)) {
            return;
        }

        const selfId = room.dataset.selfParticipantId;
        if (!selfId) {
            return;
        }

        const slot = document.querySelector('.pp-slot[data-participant-id="' + selfId + '"]');
        if (!(slot instanceof HTMLElement)) {
            return;
        }

        slot.classList.toggle('pp-slot--voted', voted);
        this.ownSlotAnimatedAt = Date.now();
        this.playSlotVoteAnimation(slot, voted);
    }

    playSlotVoteAnimation(slot, voted) {
        slot.classList.remove('pp-slot--vote-in', 'pp-slot--vote-out');
        slot.classList.add(voted ? 'pp-slot--vote-in' : 'pp-slot--vote-out');

        const shell = slot.querySelector('.pp-slot__card-shell--back');
        if (!(shell instanceof HTMLElement)) {
            return;
        }

        const cleanup = () => {
            slot.classList.remove('pp-slot--vote-in', 'pp-slot--vote-out');
        };

        shell.addEventListener('animationend', cleanup, { once: true });
        window.setTimeout(cleanup, 400);
    }

    toggleQueue(event) {
        event?.preventDefault();

        const drawer = document.querySelector('[data-queue-drawer]');
        if (!(drawer instanceof HTMLElement)) {
            return;
        }

        const open = drawer.classList.toggle('is-open');
        const toggle = drawer.querySelector('.pp-queue-drawer__toggle');
        if (toggle instanceof HTMLElement) {
            toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        }
    }

    restoreQueueDrawer() {
        if (!this.queueDrawerWasOpen) {
            return;
        }

        const drawer = document.querySelector('[data-queue-drawer]');
        if (drawer instanceof HTMLElement) {
            drawer.classList.add('is-open');
            const toggle = drawer.querySelector('.pp-queue-drawer__toggle');
            if (toggle instanceof HTMLElement) {
                toggle.setAttribute('aria-expanded', 'true');
            }
        }

        this.queueDrawerWasOpen = false;
    }

    openDialog(id) {
        const dialog = document.getElementById(id);
        if (!(dialog instanceof HTMLDialogElement)) {
            return;
        }

        if (id === 'pp-invite-dialog') {
            this.prepareInviteDialog(dialog);
        }

        if (typeof dialog.showModal === 'function') {
            dialog.showModal();
        }
    }

    closeDialog(dialog) {
        if (dialog instanceof HTMLDialogElement && dialog.open) {
            dialog.close();
        }
    }

    prepareInviteDialog(dialog) {
        const shareRoom = document.getElementById('poker-room');
        const shareUrl = shareRoom instanceof HTMLElement ? shareRoom.dataset.shareUrl : '';
        const field = dialog.querySelector('#pp-invite-url-field');
        const canvas = dialog.querySelector('#pp-invite-qr-canvas');

        if (field instanceof HTMLInputElement && shareUrl) {
            field.value = shareUrl;
        }

        if (canvas instanceof HTMLCanvasElement && shareUrl) {
            this.drawSimpleQr(canvas, shareUrl);
        }
    }

    activateSettingsTab(name) {
        const dialog = document.getElementById('pp-settings-dialog');
        this.activateTabbedPanels('data-settings-tab', 'data-settings-panel', name, dialog);
    }

    activateEntryTab(name) {
        const dialog = document.getElementById('pp-start-dialog');
        this.activateTabbedPanels('data-entry-tab', 'data-entry-panel', name, dialog);
    }

    activateTabbedPanels(tabAttribute, panelAttribute, name, root) {
        if (!name || !(root instanceof Element)) {
            return;
        }

        root.querySelectorAll('[' + tabAttribute + ']').forEach((button) => {
            const active = button.getAttribute(tabAttribute) === name;
            button.classList.toggle('is-active', active);
            button.setAttribute('aria-selected', active ? 'true' : 'false');
        });

        root.querySelectorAll('[' + panelAttribute + ']').forEach((panel) => {
            const active = panel.getAttribute(panelAttribute) === name;
            panel.classList.toggle('is-active', active);
            if (active) {
                panel.removeAttribute('hidden');
            } else {
                panel.setAttribute('hidden', '');
            }
        });
    }

    setPicked(button, picked) {
        if (picked) {
            button.classList.add('is-picked');
            button.classList.remove('is-dropping');
            button.setAttribute('aria-pressed', 'true');
            return;
        }

        button.classList.remove('is-picked');
        button.removeAttribute('aria-pressed');
    }

    clearPickedCards() {
        document.querySelectorAll('.pp-deck__card.is-picked').forEach((el) => {
            this.setPicked(el, false);
        });
    }

    applyTurboStream(html) {
        if (window.Turbo && typeof window.Turbo.renderStreamMessage === 'function') {
            window.Turbo.renderStreamMessage(html);
        }
    }

    syncStoryInputFromTitle() {
        const titleEl = document.querySelector('#story-title .pp-story');
        const input = document.getElementById('story-title-input');
        if (!titleEl || !(input instanceof HTMLInputElement)) {
            return;
        }

        const text = titleEl.textContent ? titleEl.textContent.trim() : '';
        input.value = text === 'Untitled story' ? '' : text;
    }

    copyText(text, onSuccess) {
        if (navigator.clipboard && typeof navigator.clipboard.writeText === 'function') {
            navigator.clipboard.writeText(text).then(onSuccess).catch(() => {
                this.fallbackCopy(text, onSuccess);
            });
            return;
        }

        this.fallbackCopy(text, onSuccess);
    }

    fallbackCopy(text, onSuccess) {
        const input = document.createElement('textarea');
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

    drawSimpleQr(canvas, text) {
        const ctx = canvas.getContext('2d');
        if (!ctx) {
            return;
        }

        const size = canvas.width;
        ctx.fillStyle = '#ffffff';
        ctx.fillRect(0, 0, size, size);
        ctx.fillStyle = '#111111';

        const cells = 21;
        const cell = Math.floor(size / cells);
        let hash = 0;
        for (let i = 0; i < text.length; i += 1) {
            hash = ((hash << 5) - hash + text.charCodeAt(i)) | 0;
        }

        for (let y = 0; y < cells; y += 1) {
            for (let x = 0; x < cells; x += 1) {
                hash = ((hash << 5) - hash + (x * 17) + (y * 31)) | 0;
                if ((hash & 3) === 0) {
                    ctx.fillRect(x * cell, y * cell, cell, cell);
                }
            }
        }
    }

    isConfettiEnabled() {
        const meta = document.getElementById('pp-room-meta');
        if (meta instanceof HTMLElement && meta.dataset.showConfetti === '1') {
            return true;
        }

        const root = document.getElementById('poker-room');
        return root instanceof HTMLElement && root.dataset.showConfetti === '1';
    }

    shouldCelebrateConfetti() {
        if (!this.isConfettiEnabled()) {
            return false;
        }

        const strip = document.getElementById('consensus-strip');
        if (strip instanceof HTMLElement && strip.dataset.celebrateConfetti === '1') {
            return true;
        }

        const meta = document.getElementById('pp-room-meta');
        return meta instanceof HTMLElement && meta.dataset.celebrateConfetti === '1';
    }

    syncRoomMetaFromStream() {
        const meta = document.getElementById('pp-room-meta');
        const root = document.getElementById('poker-room');
        if (!(meta instanceof HTMLElement) || !(root instanceof HTMLElement)) {
            return;
        }

        root.dataset.showConfetti = meta.dataset.showConfetti || '0';
        root.dataset.celebrateConfetti = meta.dataset.celebrateConfetti || '0';
    }

    fireConfetti() {
        if (!this.shouldCelebrateConfetti()) {
            return;
        }

        const canvas = document.createElement('canvas');
        canvas.width = window.innerWidth;
        canvas.height = window.innerHeight;
        canvas.style.position = 'fixed';
        canvas.style.inset = '0';
        canvas.style.pointerEvents = 'none';
        canvas.style.zIndex = '9999';
        document.body.appendChild(canvas);

        const ctx = canvas.getContext('2d');
        if (!ctx) {
            canvas.remove();
            return;
        }

        const colors = ['#38bdf8', '#34d399', '#fbbf24', '#f87171', '#a78bfa'];
        const particles = [];
        const count = 140;
        const gravity = 0.14;
        const ground = canvas.height + 24;

        for (let i = 0; i < count; i += 1) {
            particles.push({
                x: Math.random() * canvas.width,
                y: -24 - Math.random() * canvas.height * 0.35,
                w: 5 + Math.random() * 5,
                h: 3 + Math.random() * 4,
                vx: -2.5 + Math.random() * 5,
                vy: 1 + Math.random() * 2.5,
                color: colors[i % colors.length],
                rotation: Math.random() * Math.PI * 2,
                spin: -0.12 + Math.random() * 0.24,
            });
        }

        const tick = () => {
            ctx.clearRect(0, 0, canvas.width, canvas.height);
            let alive = false;

            for (let j = 0; j < particles.length; j += 1) {
                const p = particles[j];
                p.x += p.vx;
                p.y += p.vy;
                p.vy += gravity;
                p.vx *= 0.996;
                p.rotation += p.spin;

                if (p.y - p.h > ground) {
                    continue;
                }

                alive = true;
                ctx.save();
                ctx.translate(p.x, p.y);
                ctx.rotate(p.rotation);
                ctx.fillStyle = p.color;
                ctx.fillRect(-p.w / 2, -p.h / 2, p.w, p.h);
                ctx.restore();
            }

            if (alive) {
                window.requestAnimationFrame(tick);
                return;
            }

            canvas.remove();
        };

        window.requestAnimationFrame(tick);
    }
}
