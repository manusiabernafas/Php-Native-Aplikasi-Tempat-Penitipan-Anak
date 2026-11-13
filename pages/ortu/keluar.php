<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    unset($_SESSION['ortu']);
    session_regenerate_id(true);
}
redirect('ortu.masuk');
