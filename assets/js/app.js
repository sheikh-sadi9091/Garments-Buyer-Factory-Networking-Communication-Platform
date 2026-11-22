'use strict';

document.querySelectorAll('form[data-validate]').forEach((form) => {
    form.addEventListener('submit', (event) => {
        if (!form.checkValidity()) {
            event.preventDefault();
            form.reportValidity();
            return;
        }

        const password = form.querySelector('[name="password"]');
        const confirmation = form.querySelector('[name="password_confirmation"]');
        if (password && confirmation && password.value !== confirmation.value) {
            event.preventDefault();
            confirmation.setCustomValidity('The password confirmation does not match.');
            confirmation.reportValidity();
        }
    });

    const chat = document.querySelector('[data-chat]');
    if (chat) {
        const messages = chat.querySelector('[data-messages]');
        const form = chat.querySelector('[data-message-form]');
        const status = chat.querySelector('[data-chat-status]');
        const apiUrl = chat.dataset.apiUrl;
        const partnerId = chat.dataset.partnerId;
        const currentUserId = Number(chat.dataset.currentUserId);
        const csrfToken = chat.dataset.csrfToken;
        const downloadUrl = chat.dataset.downloadUrl;
        let loadingMessages = false;

        const renderMessages = (items) => {
            messages.replaceChildren();
            if (items.length === 0) {
                const empty = document.createElement('p');
                empty.textContent = 'No messages yet. Start the conversation.';
                messages.append(empty);
                return;
            }

            items.forEach((item) => {
                const bubble = document.createElement('article');
                bubble.className = item.sender_id === currentUserId ? 'chat-bubble is-own' : 'chat-bubble';
                const body = document.createElement('p');
                body.textContent = item.body || '';
                bubble.append(body);
                if (item.attachment_name && item.download_url) {
                    const link = document.createElement('a');
                    link.href = item.download_url;
                    link.textContent = `Download ${item.attachment_name}`;
                    bubble.append(link);
                }
                const time = document.createElement('time');
                time.dateTime = item.sent_at;
                time.textContent = new Date(item.sent_at.replace(' ', 'T')).toLocaleString();
                bubble.append(time);
                messages.append(bubble);
            });
            messages.scrollTop = messages.scrollHeight;
        };

        const loadMessages = async () => {
            if (loadingMessages) return;
            loadingMessages = true;
            try {
                const response = await fetch(`${apiUrl}?with=${encodeURIComponent(partnerId)}`, {
                    credentials: 'same-origin',
                    headers: { Accept: 'application/json' },
                });
                const data = await response.json();
                if (!response.ok) throw new Error(data.error || 'Could not load messages.');
                renderMessages(data.messages);
                status.textContent = '';
            } catch (error) {
                status.textContent = error.message || 'Could not load messages.';
            } finally {
                loadingMessages = false;
            }
        };

        form.addEventListener('submit', async (event) => {
            event.preventDefault();
            status.textContent = '';
            const button = form.querySelector('button[type="submit"]');
            button.disabled = true;
            const data = new FormData(form);
            data.set('with', partnerId);
            try {
                const response = await fetch(apiUrl, {
                    method: 'POST',
                    body: data,
                    credentials: 'same-origin',
                    headers: { 'X-CSRF-Token': csrfToken, Accept: 'application/json' },
                });
                const result = await response.json();
                if (!response.ok) throw new Error(result.error || 'Could not send this message.');
                form.reset();
                await loadMessages();
                status.textContent = 'Message sent.';
            } catch (error) {
                status.textContent = error.message || 'Could not send this message.';
            } finally {
                button.disabled = false;
            }
        });

        loadMessages();
        window.setInterval(loadMessages, 4000);
    }

    const confirmation = form.querySelector('[name="password_confirmation"]');
    if (confirmation) {
        confirmation.addEventListener('input', () => confirmation.setCustomValidity(''));
    }
});
