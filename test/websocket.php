<?php

    require 'Socket.php';

    $socket = (new Socket('127.0.0.1', 9999));

    $socket->create()->setOption(SO_REUSEADDR, 1)->bind()->listen()->ws();