from pathlib import Path
root=Path('C:/xampp/htdocs/smartqms')
path=root/'views/staff/dashboard.php'
source=path.read_text(encoding='utf-8-sig')
start=source.index('                <td>\n                  <div class="staff-row-actions"')
end=source.index('                </td>',start)+len('                </td>')
actions=source[start:end]
actions=actions.replace('                <td>','').replace('                </td>','')
source=source[:start]+source[end:]
marker='    <section class="staff-ops-panel staff-waiting-panel'
position=source.index(marker)
section_end=source.rfind('    </section>',0,position)
source=source[:section_end]+'      <?php if ($current): ?><footer class="staff-current-actions">'+actions+'\n      </footer><?php endif; ?>\n'+source[section_end:]
source=source.replace('<th class="text-end">Actions</th>','').replace('colspan="7"','colspan="6"')
# Keep the active ticket ahead of the supporting summary metrics.
start=source.index('    <section class="staff-kpi-grid')
end=source.index('    </section>',start)+len('    </section>')
kpis=source[start:end]
source=source[:start]+source[end:]
position=source.index(marker)
source=source[:position]+kpis+'\n\n'+source[position:]
path.write_text(source,encoding='utf-8')

path=root/'views/public/tracker.php'
source=path.read_text(encoding='utf-8-sig')
start=source.index('            <article class="card public-ticket-card">')
end=source.index('\n            <article class="card public-ticket-qr-card">',start)
source=source[:start]+'''            <article class="card public-ticket-card"><div class="card-body p-4 p-md-5">
              <div class="public-ticket-number-block"><span><?= $isScheduled ? 'Queue number after check-in' : 'Your queue number' ?></span><strong data-ticket-number data-queue-number><?= htmlspecialchars($projection['ticket_number'] ?: 'Not assigned yet') ?></strong></div>
              <dl class="public-queue-summary">
                <div><dt>People ahead</dt><dd data-people-ahead><?= $isScheduled ? 'After check-in' : number_format($projection['people_ahead']) ?></dd></div>
                <div><dt>Estimated wait</dt><dd data-wait-display><?= $isScheduled ? 'After check-in' : ($hasWaitEstimate ? '<span data-wait-minutes>' . number_format((float) $waitValue, 1) . '</span> min' : 'Temporarily unavailable') ?></dd></div>
              </dl>
              <p class="public-wait-note">Waiting times are estimates and may change as clients are served.</p>
              <dl class="public-ticket-details mt-4">
                <div><dt>Counter</dt><dd data-counter-label><?= htmlspecialchars($projection['counter_label'] ?: 'Assigned when called') ?></dd></div>
                <div><dt>Status</dt><dd data-status-detail><?= $isScheduled ? 'Visit reserved' : htmlspecialchars(ucwords(str_replace('-', ' ', $projection['status']))) ?></dd></div>
                <div><dt>Visit date</dt><dd><?= htmlspecialchars(date('F j, Y', strtotime((string) $projection['visit_date']))) ?></dd></div>
                <div><dt>Reference</dt><dd><?= htmlspecialchars($projection['reference_number']) ?></dd></div>
              </dl>
            </div></article>'''+source[end:]
source=source.replace('<h2 class="h5">Private tracker QR</h2>','<h2 class="h5">Keep your place handy</h2>')
source=source.replace("<?php endif; ?></div></article>\n          </div>","<?php else: ?><p class=\"public-wait-note\">Keep the private link to this page so you can return to your tracker.</p><?php endif; ?></div></article>\n          </div>",1)
path.write_text(source,encoding='utf-8')
