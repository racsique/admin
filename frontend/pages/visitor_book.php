<?php
require_once '../../backend/includes/auth.php';
include '../../backend/config/database.php';
include '../../backend/includes/header.php';
include '../../backend/includes/navbar.php';
?>
<div class="dashboard-layout">
    <?php include '../../backend/includes/sidebar.php'; ?>

<main class="dashboard-content">

    <div class="dashboard-header">
        <div>
            <p class="dashboard-label">VISITOR BOOK</p>
            <h1>Visitor Book</h1>
            <p>
                Live log of incoming visit requests submitted through the client form.
                <span id="last-updated" class="book-updated"></span>
            </p>
        </div>

        <div class="book-live-badge">
            <span class="book-live-dot"></span>
            LIVE
            <span id="request-count" class="book-count">0</span>
        </div>
    </div>

    <div class="recent-section">
        <div class="visitor-table">
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Visitor</th>
                        <th>Contact</th>
                        <th>Department</th>
                        <th>Purpose</th>
                        <th>Submitted</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody id="visitor-book-body">
                <?php
                $result = $conn->query(
                    "SELECT id, full_name, email, phone, department, person_to_visit,
                            purpose, visit_date, time_in, status, created_at
                     FROM visitors
                     ORDER BY id DESC
                     LIMIT 100"
                );

                if ($result && $result->num_rows > 0):
                    while ($visitor = $result->fetch_assoc()):
                        $initials = '';
                        foreach (explode(' ', trim($visitor['full_name'])) as $part) {
                            if ($part !== '') {
                                $initials .= strtoupper(substr($part, 0, 1));
                            }
                        }
                        $initials = substr($initials, 0, 2);
                ?>
                    <tr data-id="<?php echo (int)$visitor['id']; ?>">
                        <td><?php echo (int)$visitor['id']; ?></td>
                        <td>
                            <div class="visitor-name">
                                <div class="visitor-avatar">
                                    <?php echo htmlspecialchars($initials); ?>
                                </div>
                                <div>
                                    <strong><?php echo htmlspecialchars($visitor['full_name']); ?></strong>
                                    <small class="book-sub"><?php echo htmlspecialchars($visitor['id_number'] ?? ''); ?></small>
                                </div>
                            </div>
                        </td>
                        <td>
                            <div><?php echo htmlspecialchars($visitor['email']); ?></div>
                            <small class="book-sub"><?php echo htmlspecialchars($visitor['phone']); ?></small>
                        </td>
                        <td><?php echo htmlspecialchars($visitor['department'] ?: $visitor['person_to_visit']); ?></td>
                        <td class="book-purpose"><?php echo htmlspecialchars($visitor['purpose']); ?></td>
                        <td>
                            <?php echo date('M j, Y', strtotime($visitor['created_at'])); ?><br>
                            <small class="book-sub"><?php echo date('h:i A', strtotime($visitor['created_at'])); ?></small>
                        </td>
                        <td>
                            <?php
                            $status_class = 'checked-out';
                            if ($visitor['status'] === 'Inside') {
                                $status_class = 'inside';
                            } elseif ($visitor['status'] === 'Expected') {
                                $status_class = 'expected';
                            }
                            ?>
                            <span class="status <?php echo $status_class; ?>">
                                <span></span>
                                <?php echo htmlspecialchars($visitor['status']); ?>
                            </span>
                        </td>
                    </tr>
                <?php
                    endwhile;
                else:
                ?>
                    <tr id="book-empty-row">
                        <td colspan="7">No visitor requests yet.</td>
                    </tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</main>
</div>

<style>
    .book-updated {
        color: #777d91;
        font-size: 13px;
    }

    .book-live-badge {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        background: #fff;
        border: 1px solid #e4dcff;
        border-radius: 30px;
        padding: 10px 18px;
        font-size: 13px;
        font-weight: 700;
        color: #24b47e;
    }

    .book-live-dot {
        width: 9px;
        height: 9px;
        border-radius: 50%;
        background: #24b47e;
        animation: book-pulse 1.6s ease-in-out infinite;
    }

    @keyframes book-pulse {
        0%, 100% { opacity: 1; }
        50% { opacity: 0.35; }
    }

    .book-count {
        background: #6c4df6;
        color: #fff;
        border-radius: 20px;
        padding: 2px 10px;
        font-size: 12px;
    }

    .book-sub {
        display: block;
        color: #999;
        font-size: 12px;
    }

    .book-purpose {
        max-width: 260px;
        color: #555;
    }
</style>

<script>
(function () {
    var API_URL = '../../backend/api/visitor_book.php';
    var POLL_MS = 10000;
    var tbody = document.getElementById('visitor-book-body');
    var countEl = document.getElementById('request-count');
    var updatedEl = document.getElementById('last-updated');

    function esc(value) {
        var div = document.createElement('div');
        div.textContent = value == null ? '' : value;
        return div.innerHTML;
    }

    function statusClass(status) {
        if (status === 'Inside') return 'inside';
        if (status === 'Expected') return 'expected';
        return 'checked-out';
    }

    function initials(name) {
        return name.trim().split(/\s+/)
            .map(function (p) { return p.charAt(0).toUpperCase(); })
            .join('').substring(0, 2);
    }

    function formatSubmitted(createdAt) {
        var d = new Date(createdAt.replace(' ', 'T'));
        if (isNaN(d)) return esc(createdAt);
        var date = d.toLocaleDateString(undefined, { month: 'short', day: 'numeric', year: 'numeric' });
        var time = d.toLocaleTimeString(undefined, { hour: 'numeric', minute: '2-digit' });
        return esc(date) + '<br><small class="book-sub">' + esc(time) + '</small>';
    }

    function rowHtml(v) {
        return '<tr data-id="' + v.id + '">' +
            '<td>' + v.id + '</td>' +
            '<td><div class="visitor-name">' +
                '<div class="visitor-avatar">' + esc(initials(v.full_name)) + '</div>' +
                '<div><strong>' + esc(v.full_name) + '</strong>' +
                '<small class="book-sub">' + esc(v.id_number || '') + '</small></div>' +
            '</div></td>' +
            '<td><div>' + esc(v.email) + '</div><small class="book-sub">' + esc(v.phone) + '</small></td>' +
            '<td>' + esc(v.department || v.person_to_visit || '') + '</td>' +
            '<td class="book-purpose">' + esc(v.purpose) + '</td>' +
            '<td>' + formatSubmitted(v.created_at) + '</td>' +
            '<td><span class="status ' + statusClass(v.status) + '"><span></span>' +
                esc(v.status || '') + '</span></td>' +
        '</tr>';
    }

    var knownIds = {};
    tbody.querySelectorAll('tr[data-id]').forEach(function (tr) {
        knownIds[tr.getAttribute('data-id')] = true;
    });

    function refresh() {
        fetch(API_URL)
            .then(function (res) {
                if (!res.ok) throw new Error('HTTP ' + res.status);
                return res.json();
            })
            .then(function (data) {
                if (!data.success) return;

                countEl.textContent = data.count;
                updatedEl.textContent = '· Updated ' +
                    new Date().toLocaleTimeString([], { hour: 'numeric', minute: '2-digit', second: '2-digit' });

                var fresh = data.requests.filter(function (v) { return !knownIds[v.id]; });
                if (fresh.length === 0) return;

                var emptyRow = document.getElementById('book-empty-row');
                if (emptyRow) emptyRow.remove();

                fresh.sort(function (a, b) { return b.id - a.id; });
                fresh.forEach(function (v) { knownIds[v.id] = true; });

                var html = fresh.map(rowHtml).join('');
                tbody.insertAdjacentHTML('afterbegin', html);

                while (tbody.rows.length > 100) {
                    tbody.deleteRow(tbody.rows.length - 1);
                }
            })
            .catch(function () {
                updatedEl.textContent = '· update failed, retrying…';
            });
    }

    refresh();
    setInterval(refresh, POLL_MS);
})();
</script>

<?php include '../../backend/includes/footer.php'; ?>
