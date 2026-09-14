

/*
 * 前端入口文件：先加载全站 CSS，再初始化聊天输入框、表情选择器和手机端好友面板。
 * 这里使用原生 JavaScript，没有使用额外前端框架。
 */

import './styles/base.css';

import './styles/layout.css';

import './styles/components.css';

import './styles/pages/home.css';

import './styles/pages/community.css';

import './styles/pages/task-form.css';


import './styles/pages/forms.css';

import './styles/pages/admin.css';

import './styles/pages/banners.css';

import './styles/pages/polish.css';
// 共享笔记的新页面独立维护，避免影响既有聊天和帖子布局。
import './styles/pages/notes.css';



function prepareChatPage() {
    // 找到聊天记录、输入框和字数显示；不在聊天页时这些元素可能不存在。
    const messageList = document.querySelector('[data-chat-messages]');
    const messageInput = document.querySelector('[data-chat-input]');
    const letterCounter = document.querySelector('[data-letter-counter]');

    if (messageList) {
        // 打开聊天页后自动移动到最新一封信。
        messageList.scrollTop = messageList.scrollHeight;
    }

    if (messageInput && !messageInput.dataset.ready) {
        messageInput.dataset.ready = 'true';

        const resizeInput = () => {
            // 根据文字行数自动增高输入框，但最高不超过 260 像素。
            messageInput.style.height = 'auto';
            messageInput.style.height = Math.min(messageInput.scrollHeight, 260) + 'px';
        };

        const updateLetterCounter = () => {
            if (letterCounter) {
                letterCounter.textContent = String(messageInput.value.length);
            }
        };

        messageInput.addEventListener('input', () => {
            resizeInput();
            updateLetterCounter();
        });

        resizeInput();
        updateLetterCounter();
    }
}

document.addEventListener('DOMContentLoaded', prepareChatPage);


function prepareEmojiPickers() {
    // 一个页面可能有多个评论框，因此用 querySelectorAll 逐个初始化。
    document.querySelectorAll('[data-emoji-picker]').forEach((picker) => {
        if (picker.dataset.ready) {
            return;
        }

        picker.dataset.ready = 'true';

        const toggle = picker.querySelector('[data-emoji-toggle]');
        const panel = picker.querySelector('[data-emoji-panel]');
        const field = picker.closest('.emoji-enabled-field, .message-composer');
        const textarea = field ? field.querySelector('textarea') : null;

        if (!toggle || !panel || !textarea) {
            return;
        }

        const placeholder = document.createComment('emoji-panel-position');
        panel.before(placeholder);

        const restorePanel = () => {
            if (panel.parentElement === document.body) {
                placeholder.after(panel);
            }
            panel.classList.remove('emoji-panel--mobile');
            document.body.classList.remove('emoji-panel-open');
        };

        const closePanel = () => {
            panel.hidden = true;
            toggle.setAttribute('aria-expanded', 'false');
            restorePanel();
        };

        panel.closeEmojiPanel = closePanel;

        toggle.addEventListener('click', (event) => {
            event.stopPropagation();
            const willOpen = panel.hidden;

            document.querySelectorAll('[data-emoji-panel]').forEach((otherPanel) => {
                if (otherPanel !== panel && typeof otherPanel.closeEmojiPanel === 'function') {
                    otherPanel.closeEmojiPanel();
                }
            });

            if (willOpen && window.matchMedia('(max-width: 575px)').matches) {
                // 手机端把面板临时放到 body 中，避免被父容器裁切。
                document.body.append(panel);
                panel.classList.add('emoji-panel--mobile');
                document.body.classList.add('emoji-panel-open');
            }

            panel.hidden = !willOpen;
            toggle.setAttribute('aria-expanded', willOpen ? 'true' : 'false');

            if (!willOpen) {
                restorePanel();
            }
        });

        panel.addEventListener('click', (event) => {
            event.stopPropagation();

            const emojiButton = event.target.closest('[data-emoji]');
            if (!emojiButton) {
                return;
            }

            const emoji = emojiButton.dataset.emoji;
            const start = textarea.selectionStart ?? textarea.value.length;
            const end = textarea.selectionEnd ?? textarea.value.length;

            textarea.setRangeText(emoji, start, end, 'end');
            textarea.dispatchEvent(new Event('input', { bubbles: true }));
            textarea.focus();
            closePanel();
        });

        document.addEventListener('click', closePanel);
        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') {
                closePanel();
            }
        });
    });
}
document.addEventListener('DOMContentLoaded', prepareEmojiPickers);




function prepareMobileRelationshipPanel() {
    // 聊天页的好友关系信息在手机端使用可打开的侧面板显示。
    const panel = document.querySelector('[data-mobile-relationship-panel]');
    const openButton = document.querySelector('[data-mobile-relationship-toggle]');
    const closeButton = document.querySelector('[data-mobile-relationship-close]');

    if (!panel || !openButton || panel.dataset.mobileReady) {
        return;
    }

    panel.dataset.mobileReady = 'true';

    const closePanel = () => {
        panel.classList.remove('is-mobile-open');
        openButton.setAttribute('aria-expanded', 'false');
        document.body.classList.remove('relationship-panel-open');
    };

    const openPanel = () => {
        panel.classList.add('is-mobile-open');
        openButton.setAttribute('aria-expanded', 'true');
        document.body.classList.add('relationship-panel-open');
    };

    openButton.addEventListener('click', openPanel);
    closeButton?.addEventListener('click', closePanel);

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            closePanel();
        }
    });

    window.addEventListener('resize', () => {
        if (window.innerWidth > 767) {
            closePanel();
        }
    });
}

document.addEventListener('DOMContentLoaded', prepareMobileRelationshipPanel);
