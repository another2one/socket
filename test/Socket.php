<?php
    $host="127.0.0.1";//主机地址
    $port=8888;//端口
    //设置超时时间
    set_time_limit(0);
    //创建一个Socket
    

    $count = 1;
    while(true)
    {
        //另一个Socket来处理通信
        $spawn=socket_accept($socket) or die("Couldnotacceptincomingconnection\n");//获得客户端的输入
        $input=socket_read($spawn,1024) or die("Couldnotreadinput\n");//清空输入字符串
        $input=trim($input);//处理客户端输入并返回结果
        $request = "GET /http:127.0.0.1 HTTP/1.1\r\n";
        $request .= "Host:127.0.0.1:8888\r\n";
        $request .= "Connection:close\r\n\r\n";
        $request .= "66666";
        echo $count ++ . "\n";
        socket_write($spawn,$request,strlen($request)) or die("Couldnotwriteoutput\n");//关闭
        sleep(10);
    }
    
    socket_close($spawn);
    socket_close($socket);


    class Socket implements SocketInter
    {
        public $host;
        public $port;


        public function __construct(string $host, int $port)
        {
            $this->host = $host;
            $this->port = $port;
            set_time_limit(0);  // 设置超时时间
        }

        /**
         * create a socket
         */
        public function create($host, $port)
        {
            //绑定Socket到端口
            if($socket = socket_create(AF_INET,SOCK_STREAM,0))
                throw new \Exception("Could not create socket\n");
            
            //开始监听链接
            if($result = socket_bind($socket,$host,$port))
                throw new \Exception("Couldnotbindtosocket\n");
                
            $result = socket_listen($socket,3) or die("Couldnotsetupsocketlistener\n");//acceptincomingconnections
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