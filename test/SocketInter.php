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
        public function write();

        /**
         * read
         */
        public function read();

        /**
         * close a socket
         */
        public function close();
    }