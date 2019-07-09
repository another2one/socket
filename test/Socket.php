<?php

    class Socket implements SocketInter
    {
        public $host;
        public $port;
        public $socket;


        public function __construct(string $host, int $port)
        {
            $this->host = $host;
            $this->port = $port;
            set_time_limit(0);  // 设置超时时间
        }

        /**
         * create a socket
         */
        public function create()
        {
            //绑定Socket到端口
            if( ! ($this->socket = socket_create(AF_INET,SOCK_STREAM,0) ) ) 
                throw new \Exception("Could not create socket\n");
            
            
            if(!socket_bind($this->socket,$this->host,$this->port))
                throw new \Exception("Could not bind to socket\n");

            //开始监听链接    
            if(!socket_listen($this->socket,3))
                throw new \Exception("Could not set upsocket listener\n");
        }


        public function listen()
        {
            while(true)
            {
                //另一个Socket来处理通信
                if( !($spawn = socket_accept($this->socket)) )
                    throw new \Exception("Could not acceptin coming connection\n");

                // 接受客户端输入
                $input = socket_read($spawn,1024) or die("Couldnotreadinput\n");
                $input = trim($input);
                $request = "GET /http:127.0.0.1 HTTP/1.1\r\n";
                $request .= "Host:127.0.0.1:8888\r\n";
                $request .= "Connection:close\r\n\r\n";
                $request .= "66666";
                echo $count ++ . "\n";
                socket_write($spawn,$request,strlen($request)) or die("Couldnotwriteoutput\n");//关闭
            }
        }

        /**
         * write
         */
        public function write()
        {

        }

        /**
         * read
         */
        public function read()
        {

        }

        /**
         * close a socket
         */
        public function close()
        {

        }
    }