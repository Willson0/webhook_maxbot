<template>
    <div class="inline-katex" ref="root" v-html="html">
    </div>
</template>

<script>
import katex from 'katex';
import 'katex/dist/katex.min.css';
import MarkdownIt from "markdown-it";
import {endLoading, notify} from "@/utils.js";
import hljs from 'highlight.js';
import 'highlight.js/styles/atom-one-dark.css';

export default {
    name: 'InlineKatex',
    props: {
        text: {type: String, default: ''}
    },
    data() {
        return {
            parts: [],
            fullText: "",
            html: "",
        };
    },
    mounted() {
        this.processText();
        // перехватываем copy только внутри root
        document.addEventListener('copy', this.onCopy);
    },
    beforeUnmount() {
        document.removeEventListener('copy', this.onCopy);
    },
    watch: {
        text() {
            this.processText();
        },
        html () {
            if (this.html !== "") {
                window.Telegram.WebApp.MainButton.show();
                requestAnimationFrame(() => {
                    endLoading('loading');
                })
            }
        }
    },
    methods: {
        parseToParts(s) {
            const parts = [];
            if (!s) return parts;
            let i = 0, L = s.length;
            while (i < L) {
                const ch = s[i];
                if (ch === '\\' && i + 1 < L) {
                    parts.push({type: 'text', content: s[i + 1]});
                    i += 2;
                    continue;
                }
                if (ch === '$') {
                    if (i + 1 < L && s[i + 1] === '$') {
                        let j = i + 2, found = -1;
                        while (j < L - 1) {
                            if (s[j] === '\\') {
                                j += 2;
                                continue;
                            }
                            if (s[j] === '$' && s[j + 1] === '$') {
                                found = j;
                                break;
                            }
                            j++;
                        }
                        if (found !== -1) {
                            parts.push({type: 'math', content: s.slice(i + 2, found), display: true});
                            i = found + 2;
                            continue;
                        } else {
                            parts.push({type: 'text', content: '$$'});
                            i += 2;
                            continue;
                        }
                    } else {
                        let j = i + 1, found = -1;
                        while (j < L) {
                            if (s[j] === '\\') {
                                j += 2;
                                continue;
                            }
                            if (s[j] === '$') {
                                found = j;
                                break;
                            }
                            j++;
                        }
                        if (found !== -1) {
                            parts.push({type: 'math', content: s.slice(i + 1, found), display: false});
                            i = found + 1;
                            continue;
                        } else {
                            parts.push({type: 'text', content: '$'});
                            i += 1;
                            continue;
                        }
                    }
                }
                let j = i, buf = '';
                while (j < L && s[j] !== '\\' && s[j] !== '$') {
                    buf += s[j];
                    j++;
                }
                parts.push({type: 'text', content: buf});
                i = j;
            }
            return parts;
        },

        processText() {
            const raw = this.text || '';
            const parsed = this.parseToParts(raw);

            this.parts = parsed.map(p => {
                if (p.type === 'math') {
                    let html;
                    try {
                        html = katex.renderToString(p.content, {throwOnError: false, displayMode: !!p.display});
                    } catch (e) {
                        html = `<code style="color:crimson">KaTeX error</code>`;
                    }
                    return {...p, html};
                } else return p;
            });

            for (let part of this.parts) {
                if (part.type === "text") this.fullText += "<span>" + part.content + "</span>"
                else this.fullText += `<span data-tex="${part.content}" data-display="${part.display ? '1' : '0'}" class="${part.display ? 'display-math' : ''}">${part.html}</span>`;
            }

            const md = new MarkdownIt({
                html: true,
                linkify: true,
                breaks: true,
                highlight: function (str, lang) {
                    if (lang && hljs.getLanguage(lang)) {
                        try {
                            return '<pre class="hljs"><code>' +
                                hljs.highlight(str, { language: lang, ignoreIllegals: true }).value +
                                '</code></pre>';
                        } catch (__) { }
                    }

                    return '<pre class="hljs"><code>' + md.utils.escapeHtml(str) + '</code></pre>';
                }
            });
            this.html = md.render(this.fullText);

            this.$nextTick(() => {
                requestAnimationFrame(() => {
                    this.events();
                });
            });
        },

        // обработчик события copy
        onCopy(e) {
            try {
                const sel = window.getSelection();
                if (!sel || sel.rangeCount === 0 || sel.isCollapsed) {
                    // если ничего не выделено — копируем весь исходный текст
                    e.clipboardData.setData('text/plain', this.text || '');
                    e.preventDefault();
                    return;
                }

                let result = '';
                for (let r = 0; r < sel.rangeCount; r++) {
                    const range = sel.getRangeAt(r);
                    const frag = range.cloneContents();
                    result += this.fragmentToLatex(frag);
                }

                // положим результат в буфер обмена как plain text (LaTeX)
                e.clipboardData.setData('text/plain', result);
                // опционально: можно также положить HTML-версию,
                // но тут мы хотим LaTeX главным образом
                e.preventDefault();
            } catch (err) {
                // в случае проблем — fallback: ничего не ломаем, позволим стандартному копированию
                console.error('copy handler error', err);
            }
        },

        // рекурсивно обходим DocumentFragment / Node и строим LaTeX-строку
        fragmentToLatex(node) {
            let out = '';

            const nodeType = node.nodeType;
            // TEXT_NODE
            if (nodeType === Node.TEXT_NODE) {
                out += node.nodeValue;
                return out;
            }

            // ELEMENT_NODE (включая фрагменты)
            if (nodeType === Node.ELEMENT_NODE || nodeType === Node.DOCUMENT_FRAGMENT_NODE) {
                // если элемент содержит data-tex — это наша формула
                if (node.nodeType === Node.ELEMENT_NODE) {
                    const el = node;
                    const dataTex = el.getAttribute && el.getAttribute('data-tex');
                    const dataDisplay = el.getAttribute && el.getAttribute('data-display');
                    if (dataTex != null) {
                        // при вставке используем исходный вид: $$..$$ или $..$
                        const tex = dataTex;
                        if (dataDisplay === '1' || dataDisplay === 'true') {
                            return `${tex}`;
                        } else {
                            return `${tex}`;
                        }
                    }
                }

                // иначе обходим детей в порядке
                const children = node.childNodes;
                for (let i = 0; i < children.length; i++) {
                    out += this.fragmentToLatex(children[i]);
                }
                return out;
            }

            // прочие типы — игнорируем
            return out;
        },

        // удобная кнопка: скопировать весь исходный текст в буфер
        copyOriginalToClipboard() {
            if (navigator && navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(this.text || '').catch(err => console.error(err));
            } else {
                // fallback временный: создаём textarea и копируем
                const t = document.createElement('textarea');
                t.value = this.text || '';
                document.body.appendChild(t);
                t.select();
                try {
                    document.execCommand('copy');
                } catch (e) {
                    console.error(e);
                }
                document.body.removeChild(t);
            }
        },
        events () {
            document.querySelectorAll('.markdown-body p>code').forEach((el) => {
                console.log(el.innerText);
                el.addEventListener('click', () => {
                    let textArea = document.createElement('textarea');
                    textArea.value = el.innerText;

                    document.body.appendChild(textArea);
                    textArea.select();
                    textArea.setSelectionRange(0, 99999);

                    document.execCommand('copy');
                    document.body.removeChild(textArea);

                    notify("Успешно скопировано");
                });
            });
            document.querySelectorAll('.markdown-body pre>code').forEach((el) => {
                let block = document.createElement('div');
                block.innerHTML = "<svg xmlns=\"http://www.w3.org/2000/svg\" viewBox=\"0 0 24 24\" fill=\"none\">\n" +
                    "<path d=\"M15.24 2H11.3458C9.58159 1.99999 8.18418 1.99997 7.09054 2.1476C5.96501 2.29953 5.05402 2.61964 4.33559 3.34096C3.61717 4.06227 3.29833 4.97692 3.14701 6.10697C2.99997 7.205 2.99999 8.60802 3 10.3793V16.2169C3 17.725 3.91995 19.0174 5.22717 19.5592C5.15989 18.6498 5.15994 17.3737 5.16 16.312L5.16 11.3976L5.16 11.3024C5.15993 10.0207 5.15986 8.91644 5.27828 8.03211C5.40519 7.08438 5.69139 6.17592 6.4253 5.43906C7.15921 4.70219 8.06404 4.41485 9.00798 4.28743C9.88877 4.16854 10.9887 4.1686 12.2652 4.16867L12.36 4.16868H15.24L15.3348 4.16867C16.6113 4.1686 17.7088 4.16854 18.5896 4.28743C18.0627 2.94779 16.7616 2 15.24 2Z\" fill=\"#1C274C\"/>\n" +
                    "<path d=\"M6.6001 11.3974C6.6001 8.67119 6.6001 7.3081 7.44363 6.46118C8.28716 5.61426 9.64481 5.61426 12.3601 5.61426H15.2401C17.9554 5.61426 19.313 5.61426 20.1566 6.46118C21.0001 7.3081 21.0001 8.6712 21.0001 11.3974V16.2167C21.0001 18.9429 21.0001 20.306 20.1566 21.1529C19.313 21.9998 17.9554 21.9998 15.2401 21.9998H12.3601C9.64481 21.9998 8.28716 21.9998 7.44363 21.1529C6.6001 20.306 6.6001 18.9429 6.6001 16.2167V11.3974Z\" fill=\"#1C274C\"/>\n" +
                    "</svg>" +
                    "<div>скопировать</div>";
                block.classList.add("codeHeader");
                el.parentNode.prepend(block);

                block.addEventListener('click', () => {
                    let textArea = document.createElement('textarea');
                    textArea.value = block.parentNode.querySelector('code').innerText;

                    document.body.appendChild(textArea);
                    textArea.select();
                    textArea.setSelectionRange(0, 99999);

                    document.execCommand('copy');
                    document.body.removeChild(textArea);

                    notify("Успешно скопировано");
                });
            });
            document.querySelectorAll('a').forEach((el) => {
                el.target = "_blank";
            });
        },
    }
};
</script>

<style scoped>
.inline-katex {
    line-height: 1.4;
}

.display-math {
    display: block;
    margin: 0.5em 0;
}
</style>
