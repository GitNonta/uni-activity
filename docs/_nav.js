/**
 * _nav.js — Shared navigation for all docs/ HTML diagram pages.
 * 
 * To add a new page: just add an entry to the PAGES array below.
 * All pages automatically pick up the change — no need to touch HTML files.
 */
(function () {
  'use strict';

  const PAGES = [
    { file: 'class-diagram.html',              label: 'Class Diagram',              group: '📐 Diagrams', icon: '📘' },
    { file: 'er-diagram.html',                 label: 'ER Diagram',                 group: '📐 Diagrams', icon: '🗄️' },
    { file: 'use-case-diagram.html',           label: 'Use Case Diagram',           group: '📐 Diagrams', icon: '🎯' },
    { file: 'sequence-diagram.html',           label: 'Check-in Sequence',          group: '📐 Diagrams', icon: '🔄' },
    { file: 'student-sequence-diagram.html',   label: 'Registration Sequence',      group: '📐 Diagrams', icon: '📋' },
    { file: 'checkin-hours-sequence.html',     label: 'Check-in Hours Sequence',    group: '📐 Diagrams', icon: '⏰' },
    { file: 'FLOWCHART.md',                    label: 'FLOWCHARTS.md',              group: '🗺️ Flowcharts', icon: '📊' },
    { file: 'SCREEN-FLOW.md',                  label: 'SCREEN-FLOW.md',             group: '🗺️ Flowcharts', icon: '📱' },
    { file: 'flowchart.svg',                   label: 'System Flowchart (SVG)',      group: '🗺️ Flowcharts', icon: '🗺️' },
    { file: 'diagrams/01-student-journey.svg', label: 'Student Journey SVG',        group: '🗺️ Flowcharts', icon: '🚶' },
    { file: 'diagrams/02-checkin-detail.svg',  label: 'Check-in Detail SVG',        group: '🗺️ Flowcharts', icon: '🔍' },
    { file: 'diagrams/03-chat-screens.svg',    label: 'Chat Screens SVG',           group: '🗺️ Flowcharts', icon: '💬' },
    { file: 'diagrams/guide-01-browse.svg',    label: 'Guide ① Browse',             group: '📖 Guides', icon: '📖' },
    { file: 'diagrams/guide-02-preregister.svg', label: 'Guide ② Pre-register',     group: '📖 Guides', icon: '📝' },
    { file: 'diagrams/guide-03-approval.svg',  label: 'Guide ③ Approval',           group: '📖 Guides', icon: '✅' },
    { file: 'diagrams/guide-04-success.svg',   label: 'Guide ④ Success',            group: '📖 Guides', icon: '🎉' },
  ];

  const seen = new Set();
  const unique = PAGES.filter(p => { if (seen.has(p.file)) return false; seen.add(p.file); return true; });
  const currentFile = window.location.pathname.split('/').pop() || 'index.html';

  const groups = {};
  for (const page of unique) {
    if (!groups[page.group]) groups[page.group] = [];
    groups[page.group].push(page);
  }

  let html = '<h2>🔗 Navigation</h2>';
  for (const [label, pages] of Object.entries(groups)) {
    html += '<div style="margin-bottom:8px"><strong style="font-size:12px;color:#6b7280">' + label + '</strong></div>';
    for (const page of pages) {
      const active = (page.file === currentFile);
      html += '<a href="./' + page.file + '"' + (active ? ' style="border-color:#2563eb;background:#eff6ff;font-weight:700"' : '') + '>' + page.icon + ' ' + page.label + '</a>';
    }
  }

  const container = document.getElementById('nav-links');
  if (container) {
    container.innerHTML = html;
  }
})();
