<?php
    include 'SocketInter.php';

    function myException($exception)
    {
        if($exception instanceof \Throwable)
        {
            echo $exception->getMessage() . socket_last_error() . "\n";
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
            echo socket_last_error() . " [error] \n";
        }
        
    }

    set_exception_handler('myException');
    set_error_handler('myError');

    class Socket implements SocketInter
    {
        const BLOCK = 1;
        const NONBLOCK = 0;
        const SERVER = 1;
        const CLIENT = 0;

        public $host;
        public $port;
        public $socket;
        public $acceptSocket;
        public $connections = [];
        public $readfds = [];
        public $writefds = [];
        public $socketType = 0;
        public $config;
        public $timeout = 60;
        public $listenNum = 10000;
        public $maxconns = 1000;
        public $message;
        public $count = 0;


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


        public function bind()
        {
            // 绑定主机及端口
            if(!socket_bind($this->socket, $this->host, $this->port))
                throw new \Exception("Could not bind to socket");

            return $this;
        }


        public function connect()
        {
            if(!socket_connect($this->socket, $this->host, $this->port))
                throw new \Exception("connect fail massege:");

            return $this;
        }


        public function listen()
        {
            // 开始监听链接    
            if(!socket_listen($this->socket, $this->listenNum))
                throw new \Exception("Could not set upsocket listener");
            
            return $this;
        }


        public function createListen()
        {
            // 开始监听链接    
            if(! ($this->socket = socket_create_listen($this->port)) )
                throw new \Exception("Could not set listener");
            
            return $this;
        }


        public function setBlockStatus(int $status = self::BLOCK)
        {
            if($status)
            {
                socket_set_block($this->socket);
            }
            else
            {
                socket_set_nonblock($this->socket);
            }
            return $this;
        }


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

                $request = $this->message . '666';

                if(!socket_write($this->acceptSocket, $request, strlen($request)))
                    throw new \Exception("Could not write output");
                
                $this->echoMessage();
            }
        }


        public function select()
        {
            socket_set_nonblock($socket); // 非阻塞
            $this->connections[] = $this->socket;
            // for($jj = 0; $jj < 10; $jj++)
            while(true)
            {
                try{
                    $writefds = [];
                    $readfds = $this->connections;
                    // socket_select 阻塞 
                    if(socket_select($readfds, $writefds, $e, $this->timeout))
                    {
                        // 接受请求 如果没超过上限就加入活动的socket中
                        $newConnection = socket_accept($this->socket);
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

                        var_dump($readfds);
                        continue;

                        // 轮循读通道
                        foreach ($readfds as $rfd) {
                            // 客户端连接
                            $i = (int) $rfd;
                            // 从通道读取
                            $line = @socket_read($rfd, 2048, PHP_NORMAL_READ);
                            if ($line === false) {
                                // 读取不到内容，结束连接
                                echo "Connection closed on socket $i.\n";
                                $this->close($i);
                                continue;
                            }
                            $tmp = substr($line, -1);
                            if ($tmp != "\r" && $tmp != "\n") {
                                // 等待更多数据
                                continue;
                            }
                            // 处理逻辑
                            if($this->dealMessage(trim($line), $i) == 'break')
                                break;
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


        public function dealMessage($line, $i)
        {
            $return = '';
            if ($line == "quit") {
                echo "Client $i quit.\n";
                self::close($i);
                $return = 'break';
            }
            if ($line) {
                header('HTTP/1.1 200 OK');
                echo "Client $i >>" . ' say ' . "\n";
                //发送客户端
                socket_write($rfd,  "$i=>$line\n");
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
        public function write($message)
        {
            socket_write($this->socket, $message, strlen($message));
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
         * close a socket
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


        public function echoMessage()
        {
            var_dump($this->message);
            var_dump($this->count);
        }
    }