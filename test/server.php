<?php

    require 'Socket.php';

    $socket = (new Socket('127.0.0.1', 7777));

    $socket->create()->bind()->listen()->run();