<?php
    include 'SocketInter.php';

    function myException($exception)
    {
        if($exception instanceof \Throwable)
        {
            echo $exception->getMessage() . " line: " . $exception->getline() . ' socket error: ' . socket_last_error() . "\n";
        }else{
            echo socket_last_error() . "\n";
        }
        
    }

    function myError($exception)
    {
        if($exception instanceof \Throwable)
        {
            echo $exception->getMessage() . socket_last_error() . " [error] \n";
        }else{
            echo socket_strerror(socket_last_error()) . " [error] \n";
        }
        
    }

    // set_exception_handler('myException');
    // set_error_handler('myError');

    class Socket implements SocketInter
    {
        const BLOCK = 1;
        const NONBLOCK = 0;

        const SERVER = 1;
        const CLIENT = 0;

        public $host;  // 主机地址
        public $port; // 监听端口
        public $socket; // 当前socket
        public $acceptSocket; // 客户端
        public $connections = []; // 当前连接的socket集合
        public $socketType = 0; // 当前是客户端还是服务端
        public $config; // 配置文件
        public $selectTimeout = 10; // select 阻塞时间 null为一直阻塞
        public $wsTimeout = NULL; // select 阻塞时间 null为一直阻塞
        public $listenNum = 1000;  // socket 连接限制
        public $maxconns = 1000;  // select 连接限制
        public $message; // 接收到的消息
        public $count = 0; // 接收到的请求次数
        public $debug = true; //true为调试模式，输出log日志
        public $handshake = []; // 已经握手的socket记录


        public function __construct(string $host, int $port)
        {
            set_time_limit(0);
            $this->host = $host;
            $this->port = $port;
            $this->loadConfig();
        }


        /**
         * load config file 
         */
        public function loadConfig()
        {
           $this->config = require(__DIR__ . '/../config/config.php');
        }

        /**
         * create a socket
         */
        public function create()
        {
            // 创建socket
            $this->socket = socket_create($this->config['socket_create']['domain'], $this->config['socket_create']['type'], $this->config['socket_create']['protocol']);
            if( !$this->socket ) 
                throw new \Exception("Could not create socket: ");

            return $this;
        }

        /**
         * 绑定主机及端口
         */
        public function bind()
        {
            if(!socket_bind($this->socket, $this->host, $this->port))
                throw new \Exception("Could not bind to socket");

            return $this;
        }


        /**
         * 连接 socket 服务器
         */
        public function connect()
        {
            if(!socket_connect($this->socket, $this->host, $this->port))
                throw new \Exception("connect fail massege:");

            return $this;
        }


        /**
         * 创建监听
         */
        public function listen()
        {
            // 开始监听链接    
            if(!socket_listen($this->socket, $this->listenNum))
                throw new \Exception("Could not set upsocket listener");
            
            return $this;
        }

        /**
         * 一步创建监听绑定
         */
        public function createListen()
        {
            // 开始监听链接    
            if(! ($this->socket = socket_create_listen($this->port)) )
                throw new \Exception("Could not set listener");
            
            return $this;
        }


        /**
         * 设置socket阻塞状态
         */
        public function setBlockStatus(int $status = self::BLOCK)
        {
            if($status == self::BLOCK)
            {
                socket_set_block($this->socket);
            }
            else
            {
                socket_set_nonblock($this->socket);
            }
            return $this;
        }


        /**
         * 基础处理模型
         */
        public function run()
        {
            while(true)
            {
                //另一个Socket来处理通信
                $this->acceptSocket = socket_accept($this->socket);
            
                $this->socketType = self::SERVER;
                $this->count++;

                if( !($this->acceptSocket) )
                    throw new \Exception("Could not acceptin coming connection: ");

                // 接受客户端输入
                $this->read();

                if( $this->message === false )
                    throw new \Exception('read error: ');

                $request = $this->message . ' serve return ....';

                if(!socket_write($this->acceptSocket, $request, strlen($request)))
                    throw new \Exception("Could not write output");
            }
        }




        /**
         * websocket
         */
        public function ws()
        {
            $this->connections[] = $this->socket;

            if($this->debug)
            {
                echo "------------------  master_socket  ------------------- \n";
                var_dump($this->socket);
            }
            
            while(true)
            {
                try{

                    $readfds = $this->connections;
                    $writefds = [];
                    $e = NULL;
                    
                    echo "------------------  socket_select  ------------------- \n";
                    socket_select($readfds, $writefds, $e, $this->wsTimeout);

                    if($this->debug)
                    {
                        echo "------------------  writefds  ------------------- \n";
                        var_dump($writefds);
                        echo "------------------  readfds  ------------------- \n";
                        var_dump($readfds);
                    }
                    
                    
                    // FIXME: same IP:port connect error

                    // 轮循读通道,接受处理数据
                    foreach ($readfds as $rfd) {

                        if($this->debug)
                        {
                            echo "------------------  client info  ------------------- \n";
                            socket_getpeername($rfd, $addr, $port); 
                            var_dump($addr);
                            var_dump($port);
                        }

                        if($rfd == $this->socket)
                        {
                            $newConnection = socket_accept($this->socket);
                            if($newConnection > 0)
                            {
                                if (count($this->connections) < $this->maxconns) 
                                {
                                    $i = (int) $newConnection;
                                    $this->connections[$i] = $newConnection;
                                    $writefds[$i] = $newConnection;
                                    echo "Client $i come.\n";
                                }
                                else
                                {
                                    $message = "Server full, Try again later.\n";
                                    socket_write($writefds[$i], $message, strlen($message));
                                    unset($writefds[$i]);
                                }
                            }
                        }
                        
                        $i = (int) $rfd;
                        // 处理数据
                        if (!isset($this->handshake[$i]) || !$this->handshake[$i]){

                            $this->doHandShake($rfd, $this->readBuffer($rfd), $i);
                        }else{
                            
                            $result = $this->dealWsMessage($rfd);
                        }

                    }

                }catch(\Exception $e){
                    echo $e->getLine() . $e->getMessage() . "\n";
                }
                
                
            }
        }

        public function readBuffer($socket)
        {
            @socket_recv($socket, $buffer, 2048, 0);
            return $buffer;
        }


        /**
         * select 模型
         */
        public function select()
        {
            $this->connections[] = $this->socket;

            if($this->debug)
            {
                echo "------------------  master_socket  ------------------- \n";
                var_dump($this->socket);
            }
            

            while(true)
            {
                try{

                    $readfds = $this->connections;
                    $writefds = [];
                    /*
                    * socket_select 阻塞$this->selectTimeout 秒
                    * socket_select是阻塞，有数据请求才处理，否则一直阻塞
                    * 此处$readfds会读取到当前活动的连接
                    * 比如执行socket_select前的数据如下(描述socket的资源ID)：
                    * $socket = Resource id #4
                    * $readfds = Array
                    *       (
                    *           [0] => Resource id #5 // 客户端1
                    *           [1] => Resource id #4 // server绑定的端口的socket资源
                    *       )
                    * 调用socket_select之后，此时有两种情况：
                    * 情况一：如果是新客户端2连接，那么 $readfds = array([1] => Resource id #4),此时用于接收新客户端2连接
                    * 情况二：如果是客户端1(Resource id #5)发送消息，那么$readfds = array([1] => Resource id #5)，用户接收客户端1的数据
                    *
                    * 通过以上的描述可以看出，socket_select 有两个作用，这也是实现了IO复用
                    * 1、新客户端来了，通过 Resource id #4 介绍新连接，如情况一
                    * 2、已有连接发送数据，那么实时切换到当前连接，接收数据，如情况二
                    */
                    echo "------------------  socket_select  ------------------- \n";
                    if(socket_select($readfds, $writefds, $e, $this->selectTimeout))
                    {
                        // 当有信息发送来或者新 socket 连接过来时
                        // 接受请求 如果没超过上限就加入活动的socket中
                        $newConnection = socket_accept($this->socket);
                        if($newConnection)
                        {
                            if (count($this->connections) < $this->maxconns) 
                            {
                                $i = (int) $newConnection;
                                $this->connections[$i] = $newConnection;
                                $writefds[$i] = $newConnection;
                                echo "Client $i come.\n";
                            }
                            else
                            {
                                $message = "Server full, Try again later.\n";
                                socket_write($writefds[$i], $message, strlen($message));
                                unset($writefds[$i]);
                            }
                        }

                        if($this->debug)
                        {
                            echo "------------------  writefds  ------------------- \n";
                            var_dump($writefds);
                            echo "------------------  readfds  ------------------- \n";
                            var_dump($readfds);
                        }
                        
                        
                        

                        // 轮循读通道,接受处理数据
                        foreach ($readfds as $rfd) {

                            if($this->debug)
                            {
                                // socket_getsockname  getsockname
                                echo "------------------  client info  ------------------- \n";
                                socket_getpeername($rfd, $addr, $port); 
                                var_dump($addr);
                                var_dump($port);
                            }
                            

                            if($rfd == $this->socket) continue;

                            // 处理数据
                            $result = $this->dealMessage($rfd);
                            if($result == 'continue')   continue;
                        }
        
                        // 轮循写通道
                        foreach ($writefds as $wfd) {
                            $i = (int) $wfd;
                            socket_write($wfd, "Welcome Client $i!\n");
                        }
                    }
                }catch(\Exception $e){
                    echo $e->getLine() . $e->getMessage() . "\n";
                }
                
                
            }
        }


        public function dealWsMessage($socket)
        {
            $line = $this->readBuffer($socket);
            if($line == false)
            {
                $this->close((int) socket);
            }
            echo "client say $line \n";
            $line = $this->decode($line);
            $this->log( $line . PHP_EOL );
            $this->write('server for you ->    ' . $line, $socket);
        }



        function doHandShake($socket, $buffer, $handKey)
        {
            $this->log("\nRequesting handshake...");
            $this->log($buffer);
            list($resource, $host, $origin, $key) = $this->getHeaders($buffer);
            $this->log("Handshaking...");
            $upgrade  = "HTTP/1.1 101 Switching Protocol\r\n" .
                        "Upgrade: websocket\r\n" .
                        "Connection: Upgrade\r\n" .
                        "Sec-WebSocket-Accept: " . $this->calcKey($key) . "\r\n\r\n";  //必须以两个回车结尾
            $this->log($upgrade);
            $sent = socket_write($socket, $upgrade, strlen($upgrade));
            $this->handshake[$handKey] = true;
            $this->log("Done handshaking...");
            return true;
        }


        function getHeaders($req)
        {
            $r = $h = $o = $key = null;
            if (preg_match("/GET (.*) HTTP/"              ,$req,$match)) { $r = $match[1]; }
            if (preg_match("/Host: (.*)\r\n/"             ,$req,$match)) { $h = $match[1]; }
            if (preg_match("/Origin: (.*)\r\n/"           ,$req,$match)) { $o = $match[1]; }
            if (preg_match("/Sec-WebSocket-Key: (.*)\r\n/",$req,$match)) { $key = $match[1]; }
            return array($r, $h, $o, $key);
        }


        function log($msg = "")
        {
            if ($this->debug)  echo $msg . "\n";
        }


        function calcKey($key)
        {
            //基于websocket version 13
            $accept = base64_encode(sha1($key . '258EAFA5-E914-47DA-95CA-C5AB0DC85B11', true));
            return $accept;
        }


        function decode($buffer) 
        {
            $len = $masks = $data = $decoded = null;
            $len = ord($buffer[1]) & 127;
    
            if ($len === 126) {
                $masks = substr($buffer, 4, 4);
                $data = substr($buffer, 8);
            } 
            else if ($len === 127) {
                $masks = substr($buffer, 10, 4);
                $data = substr($buffer, 14);
            } 
            else {
                $masks = substr($buffer, 2, 4);
                $data = substr($buffer, 6);
            }
            for ($index = 0; $index < strlen($data); $index++) {
                $decoded .= $data[$index] ^ $masks[$index % 4];
            }
            return $decoded;
        }
    
        function frame($s)
        {
            $a = str_split($s, 125);
            if (count($a) == 1){
                return "\x81" . chr(strlen($a[0])) . $a[0];
            }
            $ns = "";
            foreach ($a as $o){
                $ns .= "\x81" . chr(strlen($o)) . $o;
            }
            return $ns;
        }
    


        /**
         * 处理接收到的数据
         */
        public function dealMessage($rfd)
        {
            $return = '';
            $line = trim(@socket_read($rfd, 102400));
            $i = (int) $rfd;
            $tmp = substr($line, -1);

            if ($line == false) {

                // 读取不到内容，结束连接
                echo "Connection closed on socket $i.\n";
                $this->close($i);
                $return = 'continue';

            }else if($tmp != "\r" && $tmp != "\n"){

                // 等待更多数据
                $return = 'continue';

            }else if ($line == "quit") {
                
                // 客户端发生离开消息
                echo "Client $i quit.\n";
                $ithis->close($i);
                $return = 'continue';

            }else {

                header('HTTP/1.1 200 OK');
                echo "Client $i >>" . ' say ' . "\n";
                //发送客户端
                socket_write($rfd, "$i=>$line\n");

            }
            return $return;
        }


        /**
         * https://www.php.net/manual/en/function.socket-get-option.php
         */
        public function setOption($optname, $optval , $level = SOL_SOCKET)
        {
            socket_set_option($this->socket, $level, $optname, $optval);
            return $this;
        }

        /**
         * write
         */
        public function write($message, $socket = '')
        {
            if($socket)
            {
                $message = $this->frame($message);
                socket_write($socket, $message, strlen($message));
            }
            else
            {
                socket_write($this->socket, $message, strlen($message));
            }
            return $this;
        }

        /**
         * read
         */
        public function read()
        {
            if($this->socketType == self::SERVER)
            {
                $this->message = socket_read($this->acceptSocket, 102400);
            }
            else
            {
                $this->message = socket_read($this->socket, 102400);
            }
            return $this;
        }

        /**
         * 关闭 socket 连接
         */
        public function close($i = 0)
        {
            if($i){
                socket_close($this->connections[$i]);
                unset($this->connections[$i]);
            }else{
                $this->socket && socket_close($this->socket);
                $this->acceptSocket && socket_close($this->acceptSocket);
                return $this;
            }
        }
    }