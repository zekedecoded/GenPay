<?php
// Partial: DataTables + jQuery assets (footer scripts).
// Single source of truth for the jQuery / DataTables versions used across
// every page that renders a `.js-datatable` table. Include just before the
// closing </body>, after bootstrap.bundle.min.js.
//
// Pair with the DataTables Bootstrap 5 stylesheet in the page <head>:
//   <link rel="stylesheet" href="https://cdn.datatables.net/1.13.8/css/dataTables.bootstrap5.min.css">
?>
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.8/js/dataTables.bootstrap5.min.js"></script>
<script src="<?= JS_URL ?>/admin_datatables.js"></script>
