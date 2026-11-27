(function () {
    'use strict';

    // ---------- ค่าพื้นฐาน ----------
    function meta(name) {
        var el = document.querySelector('meta[name="' + name + '"]');
        return el ? el.getAttribute('content') || '' : '';
    }

    var baseUrl = meta('app-url').replace(/\/+$/, '');

    // ---------- ข้อความ error ----------
    var statusMessages = {
        401: 'กรุณาเข้าสู่ระบบใหม่อีกครั้ง',
        403: 'บัญชีของคุณไม่มีสิทธิ์ทำรายการนี้',
        404: 'ไม่พบข้อมูลที่ต้องการ',
        409: 'ข้อมูลซ้ำกับที่มีอยู่ในระบบ',
        419: 'หน้านี้หมดอายุ กรุณารีเฟรชแล้วลองใหม่',
        422: 'กรุณากรอกข้อมูลให้ครบถ้วนและถูกต้อง'
    };

    function makeError(status, code, message, details) {
        return {
            code: code || 'HTTP_' + status,
            message: message || statusMessages[status] || 'เกิดข้อผิดพลาด กรุณาลองใหม่อีกครั้ง',
            details: details || null
        };
    }

    // ---------- ส่งคำขอ ----------
    function request(method, path, body) {
        var headers = {
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        };
        var options = { method: method, headers: headers, credentials: 'same-origin' };

        if (body !== undefined && method !== 'GET') {
            var payload = Object.assign({}, body, { _csrf: meta('csrf-token') });
            headers['Content-Type'] = 'application/json; charset=utf-8';
            options.body = JSON.stringify(payload);
        }

        return fetch(baseUrl + path, options)
            .then(function (res) {
                return res.text().then(function (text) {
                    var json = null;
                    try { json = text ? JSON.parse(text) : null; } catch (e) { json = null; }

                    if (res.status === 401) {
                        window.location.href = baseUrl + '/login';
                    }

                    if (json && typeof json === 'object' && 'data' in json) {
                        var err = json.error
                            ? makeError(res.status, json.error.code, json.error.message, json.error.details)
                            : (res.ok ? null : makeError(res.status));
                        return { ok: res.ok && !err, status: res.status, data: json.data, error: err };
                    }

                    return {
                        ok: false,
                        status: res.status,
                        data: null,
                        error: makeError(res.status, res.ok ? 'INVALID_RESPONSE' : null, res.ok ? 'รูปแบบข้อมูลจากเซิร์ฟเวอร์ไม่ถูกต้อง' : null)
                    };
                });
            })
            .catch(function () {
                return { ok: false, status: 0, data: null, error: makeError(0, 'NETWORK_ERROR', 'เชื่อมต่อเซิร์ฟเวอร์ไม่ได้ กรุณาตรวจสอบอินเทอร์เน็ต') };
            });
    }

    // ---------- ใช้งาน ----------
    window.Api = {
        url: function (path) { return baseUrl + path; },
        get: function (path) { return request('GET', path); },
        post: function (path, body) { return request('POST', path, body || {}); },
        remove: function (path) { return request('POST', path + '/delete', {}); }
    };
})();
