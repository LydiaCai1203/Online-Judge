/*
 * Copyright 2017 Liming Jin
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

/**
 * Created by Liming on 2017/1/18.
 */
"use strict";
let editor = null;
let editor_edited = false;
(() => {
    if(document.getElementById('editor')) {
        //编辑器初始化
        editor = ace.edit('editor');
        editor.setTheme("ace/theme/monokai");
        editor.setFontSize('16px');
        editor.setOption("vScrollBarAlwaysVisible", true);
        editor.$blockScrolling = Infinity;
        editor.session.on('change', () => {
            editor_edited = true;
        });

        //代码下载保存功能
        document.getElementById('download').addEventListener('click', () => {
            let a = document.createElement('a');
            let file = new Blob([editor.getValue()], {type: 'plain/text'});
            let event = document.createEvent('MouseEvents');
            event.initEvent('click', false, false);
            a.download = document.title + '.' + constant.language_type[language.value][6];
            a.href = URL.createObjectURL(file);
            a.dispatchEvent(event);
        });

        //切换编程语言修改模板
        let language = document.getElementById('language');
        language.innerHTML = '';
        for(let i = 0; i < constant.language_type.length; ++i) {
            let option = document.createElement('option');
            option.value = i;
            option.innerHTML = constant.language_type[i][0];
            language.appendChild(option);
        }
        let change = () => {
            setCookie('_language', language.value, 2592000000);
            editor.session.setMode(constant.language_type[language.value][1]);
            if(!editor_edited) {
                editor.setValue(constant.language_type[language.value][2]);
                editor.navigateTo(constant.language_type[language.value][3], constant.language_type[language.value][4]);
                editor.selection.selectTo(constant.language_type[language.value][3], constant.language_type[language.value][5]);
                editor_edited = false;
            }
            editor.focus();
        };
        let _language = getCookie('_language');
        if(_language) {
            language.value = _language;
        } else {
            language.value = 0;
            setCookie('_language', 0, 2592000000);
        }
        change();
        language.addEventListener('change', change);

        //刷新页面临时保存代码
        let _key = '_qS_' + getQuery('id');
        window.addEventListener('beforeunload', () => {
            if(editor_edited) {
                let pos = editor.selection.getCursor();
                let content = editor.getValue();
                if(content == '') {
                    sessionStorage.removeItem(_key);
                    return;
                }
                for(let code of constant.language_type) {
                    if(content == code[2]) {
                        sessionStorage.removeItem(_key);
                        return;
                    }
                }
                sessionStorage.setItem(_key, pos.row + ' ' + pos.column + "\n" + content);
            }
        });

        //恢复代码
        let saved_code = sessionStorage.getItem(_key);
        if(saved_code) {
            let l = saved_code.indexOf("\n");
            editor.setValue(saved_code.substr(l + 1));
            l = saved_code.substr(0, l).split(' ');
            editor.navigateTo(l[0], l[1]);
            editor.focus();
        }

        //监听结果事件
        let JudgeResultListened;
        let ListenJudgeResult = () => {
            window.messageServer.addType('JudgeResult', (msg) => {
                if(msg.hasOwnProperty('code')) {
                    let row = history.insertRow(1);
                    row.insertCell().innerHTML = msg.id;
                    row.insertCell().innerHTML = constant['language_type'][msg.language][0];
                    row.insertCell().innerHTML = `<button type="button" data-id="${msg.id}" data-language="${msg.language}">查看</button>`;
                    row.insertCell().innerHTML = constant['judge_status'][msg.code][1] + (msg.hasOwnProperty('info') ? `<span class="error_tip">？<template>${msg.info}</template></span>` : '');
                    row.insertCell().innerHTML = msg.time;
                }
            });
            JudgeResultListened = true;
            window.messageServer.addEvent('close', () => {
                JudgeResultListened = false;
            });
        };
        ListenJudgeResult();

        //代码提交
        let btnSubmit = document.getElementById('submit');
        btnSubmit.addEventListener('click', () => {
            btnSubmit.disabled = true;
            let _timer = setTimeout(() => {
                btnSubmit.disabled = false;
                alert('请求超时，请重试！', 'w');
            }, 10e3);
            if(!window.messageServer.sendMessage({
                    'type': constantIndex(constant['message_type'], 'Judge'),
                    'qid': getQuery('id'),
                    'language': parseInt(language.value),
                    'source_code': editor.getValue()
                }, (e) => {
                    clearTimeout(_timer);
                    let _tt = 10;
                    btnSubmit.disabled = true;
                    _timer = setInterval(() => {
                        if(!_tt) {
                            clearInterval(_timer);
                            btnSubmit.innerHTML = '提交';
                            btnSubmit.disabled = false;
                        } else {
                            btnSubmit.innerHTML = '提交（' + --_tt + '）';
                        }
                    }, 1e3);
                    if(e.hasOwnProperty('code')) {
                        switch(constant['message_code'][e.code][0]) {
                            case 'TooFrequent':
                                alert('提交请求过于频繁，请过一会再试！', 'w');
                                break;
                            case 'AccessDeny':
                                alert('权限不足！请刷新并确认账号信息再试！', 'e');
                                break;
                            case 'Success':
                                alert('提交成功，正在排队', 'i', e.id);
                                break;
                        }
                    }
                })) {
                clearTimeout(_timer);
                btnSubmit.disabled = false;
            } else if(!JudgeResultListened) {
                ListenJudgeResult();
            }
        });

        //查看历史代码
        let history = document.getElementById('history');
        history.addEventListener('click', (e) => {
            if(e.target.dataset.id && e.target.dataset.language) {
                popWindow(800, 500, 'View Code - ' + e.target.dataset.id, `/code.php?id=${e.target.dataset.id}&language=${e.target.dataset.language}`, true);
            }
        });

        //编译错误详情
        history.addEventListener('mouseover', (e) => {
            if(e.target.classList.contains('error_tip') && !e.target.dataset.div) {
                let div = document.createElement('div');
                div.id = Date.now();
                e.target.dataset.div = div.id;
                div.innerHTML = e.target.getElementsByTagName('template')[0].innerHTML;
                div.classList.add('error_tip_box');
                div.style.left = e.clientX + document.body.scrollLeft + 20 + 'px';
                div.style.top = e.clientY + document.body.scrollTop + 20 + 'px';
                document.body.appendChild(div);
            }
        });
        history.addEventListener('mouseout', (e) => {
            if(e.target.classList.contains('error_tip') && e.target.dataset.div) {
                document.body.removeChild(document.getElementById(e.target.dataset.div));
                delete e.target.dataset.div;
            }
        });
    }
})();
