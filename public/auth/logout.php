<?php

require_once dirname(__DIR__, 2) . '/core/bootstrap.php';

require_post();
logout_user();
redirect('auth/login.php');
