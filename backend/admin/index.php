<?php

require_once __DIR__ . '/../config/bootstrap.php';

if (empty($_SESSION['admin_logged_in'])) {
    header('Location: /admin/login');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>FlexaBiz Portfolio CMS</title>
    <style>
        :root {
            color: #172033;
            background: #f5f7fb;
            font-family: Arial, sans-serif;
            font-synthesis: none;
        }
        * { box-sizing: border-box; }
        body { margin: 0; min-width: 320px; }
        button, input, select, textarea { font: inherit; }
        button { cursor: pointer; }
        .layout { min-height: 100vh; display: grid; grid-template-columns: 236px minmax(0, 1fr); }
        .sidebar { background: #111a2d; color: white; padding: 24px 16px; }
        .sidebar h1 { font-size: 1.05rem; margin: 0 0 28px; padding: 0 10px; }
        .nav { display: grid; gap: 6px; }
        .nav button {
            text-align: left; border: 0; background: transparent; color: #cad2e0;
            border-radius: 8px; padding: 11px 12px;
        }
        .nav button:hover, .nav button[aria-current="page"] { color: white; background: #27344c; }
        .sidebar h1 { line-height: 1.35; }
        .main { min-width: 0; }
        .topbar {
            height: 72px; background: white; border-bottom: 1px solid #e2e8f0;
            display: flex; align-items: center; justify-content: space-between; gap: 16px; padding: 0 28px;
        }
        .topbar strong { font-size: 1rem; }
        .logout { background: white; border: 1px solid #cbd5e1; border-radius: 8px; padding: 9px 13px; }
        .content { max-width: 1440px; margin: 0 auto; padding: 28px; }
        .page-heading { display: flex; justify-content: space-between; align-items: center; gap: 16px; margin-bottom: 22px; }
        h2 { margin: 0; font-size: 1.45rem; }
        .subtle { color: #64748b; margin: 7px 0 0; font-size: .9rem; }
        .primary, .secondary, .danger, .small-button {
            border-radius: 7px; padding: 9px 13px; border: 1px solid transparent;
        }
        .primary { background: #4338ca; color: white; }
        .secondary, .small-button { background: white; color: #1e293b; border-color: #cbd5e1; }
        .danger { background: #fff; color: #b91c1c; border-color: #fecaca; }
        .small-button { font-size: .82rem; padding: 6px 9px; }
        .cards { display: grid; grid-template-columns: repeat(5, minmax(130px, 1fr)); gap: 14px; }
        .stat, .panel {
            background: white; border: 1px solid #e2e8f0; border-radius: 12px;
            box-shadow: 0 5px 18px rgba(15, 23, 42, .035);
        }
        .stat { padding: 18px; }
        .stat span { display: block; color: #64748b; font-size: .85rem; }
        .stat strong { display: block; font-size: 1.8rem; margin-top: 10px; }
        .panel { margin-top: 18px; overflow: hidden; }
        .panel-head { padding: 17px 18px; border-bottom: 1px solid #e2e8f0; font-weight: 700; }
        .table-wrap { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; min-width: 740px; }
        th, td { padding: 12px 14px; border-bottom: 1px solid #edf0f5; text-align: left; vertical-align: middle; }
        th { background: #f8fafc; color: #475569; font-size: .79rem; text-transform: uppercase; letter-spacing: .04em; }
        td { font-size: .9rem; }
        tr:last-child td { border-bottom: 0; }
        .actions { display: flex; flex-wrap: wrap; gap: 6px; }
        .badge { display: inline-block; padding: 4px 8px; border-radius: 999px; background: #eef2ff; color: #3730a3; font-size: .77rem; }
        .badge.off { background: #f1f5f9; color: #475569; }
        .empty { padding: 34px 18px; color: #64748b; text-align: center; }
        .notice { display: none; border-radius: 8px; padding: 11px 14px; margin-bottom: 16px; }
        .notice.visible { display: block; }
        .notice.success { background: #f0fdf4; border: 1px solid #bbf7d0; color: #166534; }
        .notice.error { background: #fef2f2; border: 1px solid #fecaca; color: #991b1b; }
        .loading { color: #64748b; padding: 28px 0; }
        .dashboard-heading { display: flex; align-items: flex-end; justify-content: space-between; gap: 20px; padding: 12px 0 30px; }
        .dashboard-heading h2 { margin: 0; color: #172033; font-size: clamp(1.8rem, 3vw, 2.4rem); letter-spacing: -.04em; }
        .dashboard-heading .subtle { margin-top: 8px; }
        .dashboard-kicker { margin: 0 0 8px; color: #4f46e5; font-size: .76rem; font-weight: 750; letter-spacing: .12em; text-transform: uppercase; }
        .dashboard-date { color: #64748b; font-size: .88rem; }
        .dashboard-section-heading { display: flex; align-items: baseline; justify-content: space-between; gap: 14px; margin-bottom: 15px; }
        .dashboard-section-heading h3 { margin: 0; font-size: 1rem; }
        .dashboard-section-heading span { color: #64748b; font-size: .82rem; }
        .dashboard-cards { grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 18px; }
        .dashboard-cards .stat { min-height: 208px; padding: 22px; }
        .stat-top { display: flex; align-items: center; gap: 11px; }
        .stat-icon { width: 38px; height: 38px; flex: 0 0 38px; display: grid; place-items: center; border-radius: 11px; color: #4338ca; background: #eef2ff; }
        .stat-icon svg { width: 19px; height: 19px; fill: none; stroke: currentColor; stroke-width: 1.8; stroke-linecap: round; stroke-linejoin: round; }
        .dashboard-cards .stat:nth-child(2) .stat-icon { color: #0f766e; background: #ccfbf1; }
        .dashboard-cards .stat:nth-child(3) .stat-icon { color: #b45309; background: #ffedd5; }
        .stat-label { color: #334155; font-size: .9rem; font-weight: 700; }
        .dashboard-cards .stat strong { margin-top: 20px; color: #172033; font-size: 2.45rem; letter-spacing: -.055em; }
        .dashboard-cards .stat p { margin: 5px 0 17px; color: #64748b; font-size: .83rem; line-height: 1.5; }
        .dashboard-link, .dashboard-note > button { display: inline-flex; align-items: center; gap: 9px; padding: 0; border: 0; color: #4338ca; background: transparent; font-size: .82rem; font-weight: 700; }
        .dashboard-link:hover, .dashboard-note > button:hover { color: #312e81; }
        .dashboard-note { display: flex; align-items: center; justify-content: space-between; gap: 20px; margin-top: 22px; padding: 22px 24px; border: 1px solid #e2e8f0; border-radius: 12px; background: linear-gradient(115deg, #fff, #f8faff); }
        .dashboard-note > div { display: flex; align-items: center; gap: 14px; }
        .dashboard-note-mark { width: 40px; height: 40px; display: grid; place-items: center; border-radius: 11px; color: white; background: #172033; font-size: 1rem; font-weight: 800; }
        .dashboard-note strong { color: #172033; font-size: .92rem; }
        .dashboard-note p { margin: 5px 0 0; color: #64748b; font-size: .84rem; line-height: 1.5; }
        .actions { align-items: center; gap: 7px; }
        .actions .small-button { white-space: nowrap; }
        .actions .small-button[data-action="view"], .actions .small-button[data-action="edit"] { color: #3730a3; border-color: #c7d2fe; background: #f8faff; }
        .actions .small-button.active-action-enable { color: #166534; border-color: #bbf7d0; background: #f0fdf4; }
        .actions .small-button.active-action-disable { color: #9a3412; border-color: #fed7aa; background: #fff7ed; }
        .actions .small-button[data-action="delete"] { color: #b91c1c; border-color: #fecaca; background: #fffafa; }
        .dialog-backdrop { position: fixed; inset: 0; z-index: 40; display: none; align-items: center; justify-content: center; overflow-y: auto; padding: 20px; background: rgba(15, 23, 42, .58); backdrop-filter: blur(3px); }
        .dialog-backdrop.open { display: flex; }
        .custom-dialog { width: min(640px, 100%); max-height: min(90vh, 880px); overflow-y: auto; border: 1px solid #e2e8f0; border-radius: 16px; background: white; box-shadow: 0 28px 80px rgba(15, 23, 42, .25); }
        .custom-dialog-head { position: sticky; top: 0; z-index: 1; display: flex; align-items: center; justify-content: space-between; gap: 16px; padding: 19px 22px; border-bottom: 1px solid #e8edf4; background: rgba(255, 255, 255, .96); }
        .custom-dialog-head h2 { margin: 0; color: #172033; font-size: 1.12rem; }
        .custom-dialog-close { width: 34px; height: 34px; border: 1px solid #dbe2ec; border-radius: 9px; color: #475569; background: white; font-size: 1.2rem; line-height: 1; }
        .custom-dialog-close:hover { color: #172033; background: #f8fafc; }
        .custom-dialog-content { padding: 22px; }
        .confirmation-message { margin: 0; color: #475569; font-size: .94rem; line-height: 1.7; }
        .dialog-backdrop[data-destructive="true"] .custom-dialog-head { border-bottom-color: #fee2e2; }
        .dialog-backdrop[data-destructive="true"] .custom-dialog-head h2 { color: #991b1b; }
        .custom-dialog-actions { display: flex; justify-content: flex-end; gap: 10px; padding: 16px 22px; border-top: 1px solid #e8edf4; background: #fbfcfe; }
        .record-media { margin-bottom: 20px; overflow: hidden; border-radius: 11px; background: #101827; }
        .record-video-preview, .record-image-preview { width: 100%; max-height: 340px; display: block; object-fit: contain; }
        .record-image-preview { max-height: 320px; background: #f1f5f9; object-fit: cover; }
        .record-media-empty { min-height: 130px; display: grid; place-items: center; padding: 22px; color: #64748b; background: #f1f5f9; font-size: .9rem; text-align: center; }
        .current-image-preview { width: auto; max-width: min(100%, 360px); height: auto; max-height: 220px; display: block; object-fit: contain; border: 1px solid #e2e8f0; border-radius: 9px; background: #f8fafc; }
        .current-video-preview { width: min(100%, 420px); max-height: 230px; display: block; aspect-ratio: 16 / 9; border: 1px solid #e2e8f0; border-radius: 9px; background: #101827; object-fit: contain; }
        .record-details { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 0 20px; margin: 0; }
        .record-detail { min-width: 0; padding: 13px 0; border-bottom: 1px solid #edf0f5; }
        .record-detail:last-child { border-bottom: 0; }
        .record-detail dt { margin-bottom: 6px; color: #64748b; font-size: .73rem; font-weight: 750; letter-spacing: .06em; text-transform: uppercase; }
        .record-detail dd { margin: 0; color: #1e293b; font-size: .9rem; line-height: 1.6; overflow-wrap: anywhere; white-space: pre-wrap; }
        .modal-backdrop {
            position: fixed; inset: 0; z-index: 20; background: rgba(15, 23, 42, .48);
            display: none; align-items: center; justify-content: center; padding: 18px;
        }
        .modal-backdrop.open { display: flex; }
        .modal {
            width: min(760px, 100%); max-height: min(90vh, 900px); overflow-y: auto;
            background: white; border-radius: 12px; box-shadow: 0 30px 80px rgba(15, 23, 42, .22);
        }
        .modal-head { position: sticky; top: 0; z-index: 1; background: white; padding: 18px 22px; border-bottom: 1px solid #e2e8f0; }
        .modal-head h2 { font-size: 1.2rem; }
        .modal-body { padding: 20px 22px; }
        .form-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 14px; }
        .form-grid.media-upload-form .order-field { grid-column: 1 / -1; }
        .field { display: grid; gap: 6px; }
        .field.full { grid-column: 1 / -1; }
        .field label { font-weight: 700; font-size: .86rem; }
        .field input, .field select, .field textarea {
            border: 1px solid #cbd5e1; border-radius: 7px; padding: 10px 11px; width: 100%; background: white;
        }
        .field textarea { min-height: 95px; resize: vertical; }
        .field.checkbox-field { display: flex; align-items: center; min-height: 40px; }
        .field label.check-row { display: inline-flex; align-items: center; gap: 10px; width: fit-content; min-height: 40px; margin: 0; cursor: pointer; }
        .field input[type="checkbox"] { flex: 0 0 18px; width: 18px; height: 18px; margin: 0; padding: 0; accent-color: #4338ca; }
        .modal-actions { display: flex; justify-content: flex-end; gap: 9px; margin-top: 20px; }
        .mobile-menu { display: none; }
        @media (max-width: 1000px) {
            .cards { grid-template-columns: repeat(3, minmax(0, 1fr)); }
        }
        @media (max-width: 720px) {
            .layout { grid-template-columns: minmax(0, 1fr); }
            .sidebar { padding: 12px; }
            .sidebar h1 { margin: 0 0 10px; }
            .nav { display: flex; overflow-x: auto; }
            .nav button { white-space: nowrap; }
            .topbar { height: 60px; padding: 0 16px; }
            .content { padding: 20px 14px; }
            .cards { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            .dashboard-heading { align-items: flex-start; flex-direction: column; padding-top: 4px; }
            .dashboard-section-heading { align-items: flex-start; flex-direction: column; gap: 5px; }
            .dashboard-cards { grid-template-columns: 1fr; }
            .dashboard-cards .stat { min-height: 0; }
            .dashboard-note { align-items: flex-start; flex-direction: column; }
            .page-heading { align-items: flex-start; flex-direction: column; }
            .form-grid { grid-template-columns: 1fr; }
            .field.full { grid-column: auto; }
            .form-grid.media-upload-form { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            .form-grid.media-upload-form > .field:not(.checkbox-field) { grid-column: 1 / -1; }
            .dialog-backdrop { padding: 12px; }
            .custom-dialog-head, .custom-dialog-content { padding: 17px; }
            .custom-dialog-actions { padding: 14px 17px; }
            .record-details { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <div class="layout">
        <aside class="sidebar">
            <h1>FlexaBiz Portfolio CMS</h1>
            <nav id="navigation" class="nav" aria-label="Admin modules"></nav>
        </aside>
        <main class="main">
            <header class="topbar">
                <strong id="welcome">FlexaBiz Portfolio CMS</strong>
                <button id="logout" class="logout" type="button">Log out</button>
            </header>
            <section id="content" class="content"><p class="loading">Loading CMS...</p></section>
        </main>
    </div>
    <div id="modal-backdrop" class="modal-backdrop" role="presentation">
        <section class="modal" role="dialog" aria-modal="true" aria-labelledby="modal-title">
            <header class="modal-head"><h2 id="modal-title">Add content</h2></header>
            <form id="record-form" class="modal-body"></form>
        </section>
    </div>
    <div id="dialog-backdrop" class="dialog-backdrop" role="presentation" data-destructive="false">
        <section class="custom-dialog" role="dialog" aria-modal="true" aria-labelledby="dialog-title" tabindex="-1">
            <header class="custom-dialog-head">
                <h2 id="dialog-title">Confirm action</h2>
                <button type="button" class="custom-dialog-close" aria-label="Close dialog" data-dialog-close>×</button>
            </header>
            <div id="dialog-content" class="custom-dialog-content"></div>
            <footer id="dialog-actions" class="custom-dialog-actions"></footer>
        </section>
    </div>
    <script src="/admin/cms.js" defer></script>
</body>
</html>
