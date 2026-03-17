// import router from "@/router.js";
import config from "@/config.json";
import axios from "axios";

export function notify (text, error) {
    let notifyContainer = document.querySelector(".notification_container");
    let div = document.createElement("div");

    if (error) {
        div.innerHTML = `<div class="notification error">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="800px" height="800px" viewBox="0 0 24 24" id="meteor-icon-kit__solid-exclamation-circle" fill="none"><path fill-rule="evenodd" clip-rule="evenodd" d="M24 12C24 18.6274 18.6274 24 12 24C5.37258 24 0 18.6274 0 12C0 5.37258 5.37258 0 12 0C18.6274 0 24 5.37258 24 12ZM10.5 7.5V12C10.5 12.8284 11.1716 13.5 12 13.5C12.8284 13.5 13.5 12.8284 13.5 12V7.5C13.5 6.67157 12.8284 6 12 6C11.1716 6 10.5 6.67157 10.5 7.5ZM12 18C12.8284 18 13.5 17.3284 13.5 16.5C13.5 15.6716 12.8284 15 12 15C11.1716 15 10.5 15.6716 10.5 16.5C10.5 17.3284 11.1716 18 12 18Z" fill="#758CA3"/></svg>
                                    <div>
                                        ${text}
                                    </div>
                                </div>`
    } else {
        div.innerHTML = `<div class="notification success">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="800px" height="800px" viewBox="0 0 24 24" id="meteor-icon-kit__solid-check-circle" fill="none"><path fill-rule="evenodd" clip-rule="evenodd" d="M12 24C18.6274 24 24 18.6274 24 12C24 5.37258 18.6274 0 12 0C5.37258 0 0 5.37258 0 12C0 18.6274 5.37258 24 12 24ZM7.56066 10.9393L10.5 13.8787L16.4393 7.93934C17.0251 7.35355 17.9749 7.35355 18.5607 7.93934C19.1464 8.52513 19.1464 9.47487 18.5607 10.0607L11.5607 17.0607C10.9749 17.6464 10.0251 17.6464 9.43934 17.0607L5.43934 13.0607C4.85355 12.4749 4.85355 11.5251 5.43934 10.9393C6.02513 10.3536 6.97487 10.3536 7.56066 10.9393Z" fill="#758CA3"/></svg>
                                    <div>
                                        ${text}
                                    </div>
                                </div>`
    }
    notifyContainer.appendChild(div);

    let height = div.querySelector(".notification").getBoundingClientRect().height + 10;
    div.style.visibility = "visible";
    div.style.transform = `translateY(-${height}px)`;

    requestAnimationFrame(() => {
        div.style.transition = "0.2s";
        div.style.transform = "";
        div.style.height = height + "px";
    });

    setTimeout(() => {
        div.style.opacity = '0';
        setTimeout (() => {
            div.remove();
        }, 200);
    }, 5000);
}

export function toLink (query, id = null, type = null, needback = 1) {
    document.body.style.overflow = "";

    if (id) router.push({ query: { s: query, id: id, type: type, needback: needback }});
    else router.push({ query: { s: query, needback: needback }});

    let overlay = document.querySelector(".image-overlay");
    if (overlay) overlay.remove();
}

export function levenshtein(a, b) {
    const matrix = [];

    for(let i = 0; i <= b.length; i++){
        matrix[i] = [i];
    }
    for(let j = 0; j <= a.length; j++){
        matrix[0][j] = j;
    }
    for(let i = 1; i <= b.length; i++){
        for(let j = 1; j <= a.length; j++){
            if(b.charAt(i-1) === a.charAt(j-1)){
                matrix[i][j] = matrix[i-1][j-1];
            } else {
                matrix[i][j] = Math.min(
                    matrix[i-1][j-1] + 1, // заменить
                    matrix[i][j-1] + 1,   // вставить
                    matrix[i-1][j] + 1    // удалить
                );
            }
        }
    }
    return matrix[b.length][a.length];
}

export function utcToLocalTime(utcString) {
    const date = new Date(utcString);

    const hours = String(date.getHours()).padStart(2, '0');
    const minutes = String(date.getMinutes()).padStart(2, '0');

    return `${hours}:${minutes}`;
}

export function showOverlay (cl) {
    document.body.style.overflow = "hidden";

    let el = document.querySelector(`.overlay.${cl}`);
    el.style.display = "";
    el.style.transform = "translateY(100%)";

    let background = document.querySelector(`.background.${cl}`);
    background.style.display = "";
    background.style.opacity = 0;

    requestAnimationFrame(() => {
        el.style.transform = "";
        background.style.opacity = "";
    });
}
export function hideOverlay (cl) {
    let el = document.querySelector(`.overlay.${cl}`);
    el.style.transform = "translateY(100%)";

    let background = document.querySelector(`.background.${cl}`);
    background.style.opacity = 0;

    setTimeout(() => {
        el.style.transform = "";
        background.style.opacity = "";
        background.style.display = "none";

        el.style.display = "none";
        document.body.style.overflow = "";
    }, 200);
}

export async function openList (event) {
    let select = event.target.closest(".store_input_select_container");
    document.querySelectorAll(".store_input_select_list").forEach(el => {
        if (el !== select.querySelector(".store_input_select_list"))
            el.classList.remove("active");
    })

    select.querySelector(".store_input_select_list").classList.toggle("active");
}
export async function hideList (event) {
    let el = event.target.closest(".store_input_select_container");
    el.querySelector(".store_input_select_list").classList.remove("active");
}

export function favourite (action, type, id, isLoading, user) {
    if (isLoading.status) return;

    isLoading.status = true;
    axios.post(config.backend + "favourite" + (action ? '' : '/delete'), {
        initData: window.Telegram.WebApp.initData,
        type: type,
        object_id: id,
    }).then((response) => {
        if (action) {
            notify("Успешно добавлено в избранное!");
            if (!user || !user.favourites || !Array.isArray(user.favourites[type])) user.favourites[type] = [];
            user.favourites[type].push(id);
        } else {
            notify("Успешно удалено из избранного!");
            user.favourites[type] = user.favourites[type].filter(el => el !== id);
        }
        this.$store.dispatch("updateUser", user);
    }).catch((error) => {
        if (error.response)
            notify(error.message, 1);
    }).finally(() => {
        isLoading.status = false;
    })
}

export function toLocalSimpleISO(date) {
    const pad = n => String(n).padStart(2, "0")
    return [
            date.getFullYear(),
            pad(date.getMonth() + 1),
            pad(date.getDate())
        ].join("-") + "T" +
        [
            pad(date.getHours()),
            pad(date.getMinutes()),
            pad(date.getSeconds())
        ].join(":") +
        "." +
        String(date.getMilliseconds()).padStart(3, "0")
}

export function complain (type, id) {
    let user = this.$store.state.user;

    let status = true;

    let string = prompt("Введите причину жалобы:");
    if (!string) return notify ("Пустая причина жалобы!", 1);

    axios.post(config.backend + "complain", {
        initData: window.Telegram.WebApp.initData,
        type: type,
        object_id: id,
        reason: string,
    }).then((response) => {
        status = true;
        notify ("Жалоба успешно отправлена!");
    }).catch(() => {
        status = false;
    });

    return status;
}

export function endLoading (cl = "loading") {
    let loading = document.querySelector("." + cl);
    console.log(loading);
    loading.style.opacity = 0;
    loading.addEventListener('transitionend', () => {
        loading.style.display = "none";
    }, {once: true});
}

export function startLoading (cl = "loading") {

}

export function copy (type, id) {
    let text = 'https://t.me/' + config.bot + '?startapp=' + type + '_' + id;
    navigator.clipboard.writeText(text)
        .then(() => {
            notify("Успешно скопировано!", 0);
        })
        .catch(err => {
            notify("Устройство не позволяет скопирвать ссылку", 1);
        });
}

export function timestampToDate(timestamp) {
    if (!timestamp) {
        return "";
    }
    const date = new Date(timestamp * 1000);

    const day = String(date.getDate()).padStart(2, '0');
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const year = date.getFullYear();

    return `${day}.${month}.${year}`;
}

export function openOverlay(overlay, background = null) {
    document.body.style.overflow = "hidden";
    let elem = document.querySelector('.' + overlay);
    elem.style.display = "";
    if (background) document.querySelector('.' + background).style.display = "";
    requestAnimationFrame(() => {
        elem.style.transform = "translateY(0)";
        if (background) document.querySelector('.' + background).style.opacity = "1";
    });
}

export function closeOverlay(overlay, background = null) {
    document.body.style.overflow = "";
    let elem = document.querySelector('.' + overlay);
    elem.style.transform = "";
    if (background) document.querySelector('.' + background).style.opacity = "";
    elem.addEventListener('transitionend', function() {
        elem.style.display = "none";
        if (background) document.querySelector('.' + background).style.display = "none";
    }, {once: true});
}