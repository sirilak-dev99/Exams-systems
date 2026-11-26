/* =========================================================
   ui.js — ตัวช่วยหน้าจอ (โหลดทุกหน้าจาก footer.php)
   ---------------------------------------------------------
   UI.openModal(id) / UI.closeModal(id)   เปิด-ปิด Modal (หรือใช้ data-modal-open / data-modal-close ไม่ต้องเขียน JS)
   UI.toast(ข้อความ, 'danger'|'info')    แจ้งเตือนมุมล่าง หายเองใน 4 วินาที
   UI.confirm(คำถาม, {detail, okText, danger})  กล่องยืนยัน → Promise<true|false>
   UI.showErrors(form, details) / UI.clearErrors(form)  แสดง/ล้าง error ใต้ช่องกรอก (details จาก API 422)
   UI.formData(form)                      อ่านค่าฟอร์มเป็น object (checkbox → 1/0)
   UI.busy(ปุ่ม, true, 'กำลังบันทึก...')  ปุ่มกำลังโหลด กันกดซ้ำ
   UI.label('status', 'draft') → 'ฉบับร่าง' · UI.statusBadge('published') · UI.thaiDate('2026-11-25 10:06')
   UI.esc(ข้อความ)                        ป้องกัน XSS ก่อนใส่ลง innerHTML

   ตัวอย่าง Modal
     <button data-modal-open="mAdd">เพิ่ม</button>
     <div class="modal" id="mAdd" ...> ... <button data-modal-close>ยกเลิก</button>
   ========================================================= */
(function (global) {
  'use strict';

  // ---------- ป้องกัน XSS: ใช้ทุกครั้งที่ใส่ข้อมูลลง innerHTML ----------
  const esc = (s) => String(s == null ? '' : s)
    .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;').replace(/'/g, '&#39;');

  // ---------- Modal ----------
  let lastFocus = null;
  const FOCUSABLE = 'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])';

  function openModal(id) {
    const m = typeof id === 'string' ? document.getElementById(id) : id;
    if (!m) return;
    lastFocus = document.activeElement;
    m.classList.add('is-open');
    document.body.classList.add('modal-open');
    const first = m.querySelector('[autofocus]') || m.querySelector(FOCUSABLE);
    if (first) first.focus();
  }
  function closeModal(id) {
    const m = typeof id === 'string' ? document.getElementById(id) : id;
    if (!m) return;
    m.classList.remove('is-open');
    if (!document.querySelector('.modal.is-open')) document.body.classList.remove('modal-open');
    if (lastFocus && lastFocus.focus) lastFocus.focus();
  }

  document.addEventListener('click', (e) => {
    const op = e.target.closest('[data-modal-open]');
    if (op) { e.preventDefault(); openModal(op.getAttribute('data-modal-open')); return; }
    const cl = e.target.closest('[data-modal-close]');
    if (cl) { e.preventDefault(); closeModal(cl.closest('.modal')); return; }
    // คลิกพื้นหลังสีเทาเพื่อปิด (ยกเว้น modal ที่ใส่ data-static)
    if (e.target.classList && e.target.classList.contains('modal') && !e.target.hasAttribute('data-static')) closeModal(e.target);
  });

  document.addEventListener('keydown', (e) => {
    const m = document.querySelector('.modal.is-open:last-of-type') || document.querySelector('.modal.is-open');
    if (!m) return;
    if (e.key === 'Escape' && !m.hasAttribute('data-static')) { closeModal(m); return; }
    if (e.key === 'Tab') {   // วน focus อยู่ใน modal
      const items = Array.prototype.filter.call(m.querySelectorAll(FOCUSABLE), (el) => el.offsetParent !== null);
      if (!items.length) return;
      const first = items[0], last = items[items.length - 1];
      if (e.shiftKey && document.activeElement === first) { e.preventDefault(); last.focus(); }
      else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); }
    }
  });

  // ---------- Toast ----------
  function toast(message, type, ms) {
    let zone = document.querySelector('.toast-zone');
    if (!zone) {
      zone = document.createElement('div');
      zone.className = 'toast-zone';
      zone.setAttribute('role', 'status');
      zone.setAttribute('aria-live', 'polite');
      document.body.appendChild(zone);
    }
    const t = document.createElement('div');
    t.className = 'toast' + (type && type !== 'success' ? ' toast--' + type : '');
    t.textContent = message;
    zone.appendChild(t);
    setTimeout(() => t.remove(), ms || 4000);
  }

  // ---------- ฟอร์ม ----------
  // details เช่น { title: 'กรุณากรอกชื่อชุดข้อสอบ' } → แสดงใต้ช่อง name="title"
  function showErrors(form, details) {
    clearErrors(form);
    if (!details) return;
    let firstEl = null;
    Object.keys(details).forEach((name) => {
      const input = form.querySelector('[name="' + name + '"]');
      if (!input) return;
      const field = input.closest('.field');
      if (!field) return;
      field.classList.add('is-invalid');
      input.setAttribute('aria-invalid', 'true');
      let box = field.querySelector('.field__error');
      if (!box) { box = document.createElement('div'); box.className = 'field__error'; field.appendChild(box); }
      if (!box.id) box.id = 'err-' + name;
      input.setAttribute('aria-describedby', box.id);
      box.textContent = details[name];
      if (!firstEl) firstEl = input;
    });
    if (firstEl) firstEl.focus();
  }
  function clearErrors(form) {
    form.querySelectorAll('.field.is-invalid').forEach((f) => f.classList.remove('is-invalid'));
    form.querySelectorAll('[aria-invalid]').forEach((i) => i.removeAttribute('aria-invalid'));
    form.querySelectorAll('.field__error').forEach((b) => { b.textContent = ''; });
  }
  // อ่านค่าฟอร์มเป็น object (checkbox → 1/0 ตาม schema)
  function formData(form) {
    const out = {};
    Array.prototype.forEach.call(form.elements, (el) => {
      if (!el.name || el.disabled) return;
      if (el.type === 'checkbox') out[el.name] = el.checked ? 1 : 0;
      else if (el.type === 'radio') { if (el.checked) out[el.name] = el.value; }
      else if (el.type === 'number') out[el.name] = el.value === '' ? null : Number(el.value);
      else out[el.name] = el.value.trim();
    });
    return out;
  }

  // ปุ่มกำลังโหลด ป้องกันกดซ้ำ
  function busy(btn, on, text) {
    if (!btn) return;
    if (on) {
      btn.dataset.label = btn.textContent;
      btn.setAttribute('aria-busy', 'true');
      btn.disabled = true;
      if (text) btn.textContent = text;
    } else {
      btn.removeAttribute('aria-busy');
      btn.disabled = false;
      if (btn.dataset.label) btn.textContent = btn.dataset.label;
    }
  }

  // ---------- ข้อความไทยของค่า enum (docs/api_contract.md ข้อ 4 และ 7) ----------
  // หมายเหตุ exams/create.php ใช้ข้อความ mode/status ต่างจากนี้เล็กน้อย — รอจอยเลือกชุดที่ใช้จริง
  const LABELS = {
    role:   { student: 'นิสิต', instructor: 'อาจารย์', academic_staff: 'เจ้าหน้าที่ฝ่ายวิชาการ', admin: 'ผู้ดูแลระบบ' },
    kind:   { quiz: 'สอบย่อย (Quiz)', midterm: 'สอบกลางภาค', final: 'สอบปลายภาค', pre_post: 'สอบก่อนเรียน / หลังเรียน' },
    mode:   { fixed: 'กำหนดข้อสอบเอง (ชุดคงที่)', blueprint: 'สุ่มข้อสอบตามโครงสร้าง (Blueprint)' },
    status: { draft: 'ฉบับร่าง', published: 'เผยแพร่แล้ว', closed: 'ปิดรับข้อสอบ', archived: 'จัดเก็บแล้ว' },
  };
  function label(group, value) {
    const g = LABELS[group];
    return (g && g[value]) || value || '';
  }

  // Badge สถานะชุดสอบ
  function statusBadge(status) {
    return '<span class="badge badge--' + esc(status) + '">' + esc(label('status', status)) + '</span>';
  }

  // วันเวลา (YYYY-MM-DD HH:MM[:SS]) → ข้อความไทย เช่น 25 พ.ย. 2569 10:06 น.
  const TH_MONTH = ['ม.ค.', 'ก.พ.', 'มี.ค.', 'เม.ย.', 'พ.ค.', 'มิ.ย.', 'ก.ค.', 'ส.ค.', 'ก.ย.', 'ต.ค.', 'พ.ย.', 'ธ.ค.'];
  function thaiDate(s, withTime) {
    if (!s) return '-';
    const m = String(s).match(/^(\d{4})-(\d{2})-(\d{2})(?:[ T](\d{2}):(\d{2}))?/);
    if (!m) return String(s);
    let out = (+m[3]) + ' ' + TH_MONTH[+m[2] - 1] + ' ' + (+m[1] + 543);
    if (withTime !== false && m[4]) out += ' ' + m[4] + ':' + m[5] + ' น.';
    return out;
  }

  // ---------- ยืนยันก่อนทำรายการ (เช่น ลบ) ----------
  //   if (await UI.confirm('ลบหลักสูตรนี้?', { detail: 'ลบแล้วกู้คืนไม่ได้', okText: 'ลบ', danger: true })) { ... }
  function confirmBox(title, opts) {
    const o = opts || {};
    return new Promise((resolve) => {
      const id = 'uiConfirm' + Date.now();
      const wrap = document.createElement('div');
      wrap.className = 'modal';
      wrap.id = id;
      wrap.setAttribute('role', 'alertdialog');
      wrap.setAttribute('aria-modal', 'true');
      wrap.setAttribute('aria-labelledby', id + 'T');
      wrap.innerHTML =
        '<div class="modal__box modal__box--sm">' +
          '<div class="modal__head"><h2 id="' + id + 'T">' + esc(title) + '</h2></div>' +
          (o.detail ? '<p class="muted">' + esc(o.detail) + '</p>' : '') +
          '<div class="modal__foot">' +
            '<button type="button" class="btn btn--secondary" data-ui-no>' + esc(o.cancelText || 'ยกเลิก') + '</button>' +
            '<button type="button" class="btn ' + (o.danger ? 'btn--danger' : 'btn--primary') + '" data-ui-yes>' + esc(o.okText || 'ยืนยัน') + '</button>' +
          '</div>' +
        '</div>';
      wrap.setAttribute('data-static', '');   // คลิกพื้นหลังไม่ปิด ต้องเลือกปุ่ม
      document.body.appendChild(wrap);
      const done = (v) => { closeModal(wrap); wrap.remove(); resolve(v); };
      wrap.querySelector('[data-ui-no]').addEventListener('click', () => done(false));
      wrap.querySelector('[data-ui-yes]').addEventListener('click', () => done(true));
      wrap.addEventListener('keydown', (e) => { if (e.key === 'Escape') done(false); });
      openModal(wrap);
      wrap.querySelector('[data-ui-no]').focus();
    });
  }

  // ---------- สิ่งที่เปิดให้หน้าเว็บใช้ (window.UI) ----------
  global.UI = { esc, openModal, closeModal, toast, confirm: confirmBox, showErrors, clearErrors, formData, busy, statusBadge, thaiDate, LABELS, label };
})(window);
