<?php
include('../mysql/db.php');
session_start();
if (!isset($_SESSION['name'])) {
  header('Location: ../index.php');
  exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Notes</title>
  <link rel="icon" type="image/png" href="../images/COJ.png">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet"/>
  <link rel="stylesheet" href="../css/styles.css">
  <link rel="stylesheet" href="../css/notes.css">
</head>
<body>

<?php $active_page = 'notes'; include('includes/sidebar.php'); ?>

<!-- MAIN -->
<div id="main">
  <div id="topbar">
    <div class="topbar-left">
      <div class="page-title">Notes</div>
      <div class="page-sub">Manage your notes and reminders</div>
    </div>
  </div>

  <div id="page-container">
    <div id="page-notes">

      <div class="notes-layout">

        <!-- LEFT: Notes List -->
        <div class="notes-list-panel">
          <div class="notes-list-head">
            <span id="notesCountLabel">All Notes (0)</span>
            <button id="addNoteBtn" title="New note">+</button>
          </div>

          <!-- Search -->
          <div class="notes-search-row">
            <input type="text" id="notesSearchInput" class="notes-search-input" placeholder="Search notes..."/>
          </div>

          <!-- Category Filter - -->
          <div class="notes-search-row">
            <select id="notesCategoryFilter" class="notes-search-input" style="cursor:pointer;">
              <option value="">All Categories</option>
              <option value="General">General</option>
              <option value="Academic">Academic</option>
              <option value="Meeting">Meeting</option>
              <option value="Concern">Concern</option>
            </select>
          </div>

          <div id="notesContainer">
            <div id="emptyState" class="notes-empty-list">
              <div style="font-size:28px;opacity:0.35;margin-bottom:8px;">📝</div>
              <p>No notes yet.<br>Click <strong>+</strong> to create one.</p>
            </div>
          </div>
        </div>

        <!-- RIGHT: Editor -->
        <div class="notes-editor-panel" id="notesEditorPanel">

          <div class="editor-placeholder" id="editorPlaceholder">
            <div style="font-size:48px;opacity:0.18;margin-bottom:16px;">📓</div>
            <div style="font-size:15px;color:var(--color-muted);font-weight:500;">
              Select a note or create a new one
            </div>
          </div>

          <div id="editorContent" style="display:none;flex-direction:column;height:100%;">
            <div class="editor-toolbar">
              <input class="editor-title-input" id="noteTitle" type="text" placeholder="Note title..."/>
              <div class="editor-actions">
                <button class="btn-sm btn-save" id="saveNoteBtn">
                  <i class="bi bi-floppy-fill"></i> Save
                </button>
                <button class="btn-sm btn-delete" id="deleteNoteBtn" style="display:none;">
                  <i class="bi bi-trash-fill"></i> Delete
                </button>
              </div>
            </div>

            <div class="editor-meta-row">
              <span>Category:</span>
              <select id="noteCategory">
                <option>General</option>
                <option>Academic</option>
                <option>Meeting</option>
                <option>Concern</option>
              </select>
            </div>

            <textarea id="noteBody" placeholder="Start writing your note here!"></textarea>
          </div>
        </div>
      </div>

      <!-- Delete Confirm Modal -->
      <div id="notesConfirmOverlay" style="display:none;position:fixed;inset:0;background:rgba(20,28,60,0.45);backdrop-filter:blur(3px);z-index:2000;align-items:center;justify-content:center;">
        <div style="background:#fff;border-radius:16px;padding:32px 36px;box-shadow:0 8px 48px rgba(61,74,138,0.22);max-width:360px;width:90%;text-align:center;">
          <div style="font-size:32px;margin-bottom:12px;">🗑️</div>
          <div style="font-size:16px;font-weight:700;margin-bottom:8px;">Delete Note?</div>
          <div style="font-size:13px;color:var(--color-muted);margin-bottom:24px;">This action cannot be undone.</div>
          <div style="display:flex;gap:10px;justify-content:center;">
            <button id="cancelDeleteBtn" style="padding:10px 24px;border:1.5px solid var(--color-border);background:#fff;border-radius:9px;font-size:13px;font-weight:600;cursor:pointer;">Cancel</button>
            <button id="confirmDeleteBtn" style="padding:10px 24px;background:var(--color-danger);color:#fff;border:none;border-radius:9px;font-size:13px;font-weight:600;cursor:pointer;">Delete</button>
          </div>
        </div>
      </div>

    </div>
  </div>
</div>

<div class="toast" id="toast"></div>
<script src="../js/nav.js"></script>
<script src="../js/notes.js"></script>
</body>
</html>