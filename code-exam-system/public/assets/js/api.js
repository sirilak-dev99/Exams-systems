/* =========================================================
   api.js — HTTP Client กลาง (fetch) ตาม docs/api_contract.md
   ทุก endpoint ตอบ { data, error }
   - สำเร็จ  → คืนค่า data
   - ผิดพลาด → throw ApiError { status, code, message, details }

   ใช้กับ route จริงใน routes/web.php (ไม่มี prefix /api/v1) — backend ตอบ JSON เพราะ api.js ส่ง Accept: application/json
     const { programs } = await API.get('/programs', { status: 'active' });
     const { program }  = await API.post('/programs', { code: 'BNS', name: '...' });
     await API.post('/programs/5/delete');              // ลบ: Router มีแค่ GET/POST
     try { ... } catch (e) { if (e.code === 'VALIDATION_ERROR') UI.showErrors(form, e.details); else UI.toast(e.message, 'danger'); }

   ค่าจาก <meta> ใน header.php
     api-base   path ของ APP_URL เช่น /code-exam-system-starter/code-exam-system/public
     login-url  หน้า Login (พาไปเมื่อได้ 401)
     csrf-token ส่งใน header X-CSRF-Token ทุกคำขอที่ไม่ใช่ GET
   ========================================================= */
(function (global) {
  'use strict';

  // ---------- 1. ค่าตั้งต้น: อ่าน <meta> · หมดเวลา 20 วินาที ----------
  const meta = (name) => {
    const el = document.querySelector('meta[name="' + name + '"]');
    return el ? el.getAttribute('content') || '' : '';
  };

  const TIMEOUT_MS = 20000;

  // ---------- 2. ข้อความ error ภาษาไทย และรหัสตาม HTTP status ----------
  // ข้อความสำรองเมื่อ backend ไม่ได้ส่ง message (contract ข้อ 3 + รหัสที่ Controller ของโปรเจกต์ใช้จริง)
  const DEFAULT_MESSAGES = {
    BAD_REQUEST: 'คำขอไม่ถูกต้อง กรุณาลองใหม่อีกครั้ง',
    UNAUTHORIZED: 'กรุณาเข้าสู่ระบบอีกครั้ง',
    INVALID_CREDENTIALS: 'อีเมล/รหัสนิสิต หรือรหัสผ่านไม่ถูกต้อง',
    FORBIDDEN: 'คุณไม่มีสิทธิ์ใช้งานส่วนนี้',
    ACCOUNT_INACTIVE: 'บัญชีผู้ใช้นี้ถูกระงับการใช้งาน กรุณาติดต่อผู้ดูแลระบบ',
    NOT_FOUND: 'ไม่พบข้อมูลที่ต้องการ อาจถูกลบไปแล้ว',
    METHOD_NOT_ALLOWED: 'ไม่รองรับการทำรายการแบบนี้',
    CONFLICT: 'ข้อมูลซ้ำหรือขัดแย้งกับข้อมูลที่มีอยู่',
    CSRF_MISMATCH: 'หน้านี้เปิดค้างนานเกินไป กรุณารีเฟรชหน้าแล้วลองใหม่',
    VALIDATION_ERROR: 'กรุณากรอกข้อมูลให้ครบถ้วนและถูกต้อง',
    INTERNAL_SERVER_ERROR: 'ระบบขัดข้องชั่วคราว กรุณาลองใหม่ภายหลัง',
    NETWORK_ERROR: 'เชื่อมต่อเครือข่ายไม่ได้ ตรวจสอบอินเทอร์เน็ตแล้วลองใหม่',
    TIMEOUT: 'ระบบตอบช้าเกินไป กรุณาลองใหม่อีกครั้ง',
    BAD_RESPONSE: 'ระบบตอบกลับในรูปแบบที่ไม่ถูกต้อง กรุณาแจ้งผู้ดูแลระบบ',
  };

  const CODE_BY_STATUS = {
    400: 'BAD_REQUEST', 401: 'UNAUTHORIZED', 403: 'FORBIDDEN', 404: 'NOT_FOUND', 405: 'METHOD_NOT_ALLOWED',
    409: 'CONFLICT', 419: 'CSRF_MISMATCH', 422: 'VALIDATION_ERROR', 500: 'INTERNAL_SERVER_ERROR',
  };

  // ---------- 3. ApiError — สิ่งที่ catch ได้เมื่อเรียก API ไม่สำเร็จ { status, code, message, details } ----------
  class ApiError extends Error {
    constructor(status, err) {
      const code = (err && err.code) || CODE_BY_STATUS[status] || 'INTERNAL_SERVER_ERROR';
      super((err && err.message) || DEFAULT_MESSAGES[code] || DEFAULT_MESSAGES.INTERNAL_SERVER_ERROR);
      this.name = 'ApiError';
      this.status = status;                         // 0 = ต่อเครือข่ายไม่ได้
      this.code = code;
      this.details = (err && err.details) || null;  // { field: 'ข้อความ' } กรณี 422
    }
  }

  // ---------- 4. แปลง object เป็น query string ----------
  // ตัดค่า null / undefined / '' ออกจาก query string
  function toQuery(params) {
    if (!params) return '';
    const q = new URLSearchParams();
    Object.keys(params).forEach((k) => {
      const v = params[k];
      if (v === null || v === undefined || v === '') return;
      if (Array.isArray(v)) v.forEach((x) => q.append(k + '[]', x));
      else q.append(k, v);
    });
    const s = q.toString();
    return s ? '?' + s : '';
  }

  // ---------- 5. ส่งคำขอ แล้วแปลงผลลัพธ์เป็น data หรือ ApiError ----------
  async function request(method, path, body, options) {
    const opt = options || {};
    const base = meta('api-base').replace(/\/+$/, '');
    const url = base + (path.charAt(0) === '/' ? path : '/' + path) + toQuery(opt.query);

    const headers = { Accept: 'application/json' };
    const isForm = typeof FormData !== 'undefined' && body instanceof FormData;
    if (body !== undefined && !isForm) headers['Content-Type'] = 'application/json; charset=utf-8';
    const csrf = meta('csrf-token');
    if (csrf && method !== 'GET') headers['X-CSRF-Token'] = csrf;
    if (API.token) headers.Authorization = 'Bearer ' + API.token;

    const ctrl = new AbortController();
    const timer = setTimeout(() => ctrl.abort(), opt.timeout || TIMEOUT_MS);

    let res;
    try {
      res = await fetch(url, {
        method,
        headers,
        credentials: 'same-origin',   // ส่ง Session Cookie ไปด้วย
        body: body === undefined ? undefined : isForm ? body : JSON.stringify(body),
        signal: ctrl.signal,
      });
    } catch (e) {
      throw new ApiError(0, { code: e.name === 'AbortError' ? 'TIMEOUT' : 'NETWORK_ERROR' });
    } finally {
      clearTimeout(timer);
    }

    let json = null;
    const text = await res.text();
    if (text) {
      try { json = JSON.parse(text); } catch (e) { json = null; }
    }

    // ไม่ใช่ { data, error } เช่น PHP พิมพ์ error เป็น HTML หรือหน้า 404 ของ Router
    if (!json || typeof json !== 'object' || !('data' in json || 'error' in json)) {
      if (global.console) console.error('[API] รูปแบบผิด', method, url, res.status, text.slice(0, 300));
      // CSRF ไม่ผ่าน: Controller ส่ง 419 แต่ Apache เปลี่ยนเป็น 500 (docs/api-check.md ข้อ 2) — ดูจากข้อความแทน
      if (/419 Page Expired/i.test(text)) throw new ApiError(419, { code: 'CSRF_MISMATCH' });
      throw new ApiError(res.status || 500, { code: res.ok ? 'BAD_RESPONSE' : undefined });
    }

    if (!res.ok || json.error) {
      const err = new ApiError(res.status, json.error);
      if (err.status === 401 && !opt.noAuthRedirect) API.onUnauthorized(err);
      throw err;
    }
    return json.data;
  }

  // ---------- 6. สิ่งที่เปิดให้หน้าเว็บใช้: API.get / API.post / API.onUnauthorized ----------
  const API = {
    ApiError,
    token: null,   // ระบบใช้ Session Cookie — ตั้งค่านี้เฉพาะถ้า backend เปลี่ยนไปใช้ Bearer Token

    get:  (path, query, opt) => request('GET', path, undefined, Object.assign({}, opt, { query })),
    post: (path, body, opt) => request('POST', path, body === undefined ? {} : body, opt),
    // Router ของโปรเจกต์มีแค่ GET/POST (แก้ไข = POST /xxx/{id}, ลบ = POST /xxx/{id}/delete)

    // ได้ 401 → พาไปหน้า Login แล้วแนบหน้าเดิมไว้ใน ?next=
    onUnauthorized() {
      const login = meta('login-url');
      if (!login || location.pathname === new URL(login, location.href).pathname) return;
      location.href = login + (login.indexOf('?') < 0 ? '?' : '&') + 'next=' + encodeURIComponent(location.pathname + location.search);
    },
  };

  global.API = API;
  global.ApiError = ApiError;
})(window);
