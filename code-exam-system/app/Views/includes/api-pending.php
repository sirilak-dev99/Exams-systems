<?php /* ---------- รอ API ---------- */ ?>
<div class="alert" role="note">
    <?= ui_icon('bolt', 22) ?>
    <div>
        <b>ส่วนนี้ยังไม่มี API จากฝั่งระบบ</b>
        <span><?= Security::escape((string)($pendingApi ?? '')) ?></span>
    </div>
</div>
