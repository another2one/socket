<?php

    require 'Socket.php';

    $socket = (new Socket('127.0.0.1', 8888));

    // $socket->createListen()->setBlockStatus(Socket::NONBLOCK)->select();
    $socket->create()->bind()->listen()->setBlockStatus(Socket::NONBLOCK)->select();