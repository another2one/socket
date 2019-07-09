<?php

    interface SocketInter
    {
        /**
         * create a socket
         */
        public function create();

        /**
         * write
         */
        public function write($message);

        /**
         * read
         */
        public function read();

        /**
         * close a socket
         */
        public function close();
    }