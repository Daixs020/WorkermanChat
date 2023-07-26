<?php
namespace app\gateway;

use think\worker\Server;
use Workerman\Lib\Timer;
use app\model\Chat;
use \GatewayWorker\Lib\Gateway as Gta;
class Gateway extends Server
{
	protected $socket = 'websocket://0.0.0.0:2346';
	protected static $timeHert = 30;
	public function onMessage($connection,$data)
	{
	   $dataArr = json_decode($data,true);
	   switch ($dataArr['type']) {
	       //表示登录绑定
	       case 'bind':
	        $connection->uid = $dataArr['uid'];
           /* 保存uid到connection的映射，这样可以方便的通过uid查找connection，私聊操作
            * 实现针对特定uid推送数据 储存用户到数据库
            */
            $chat = new Chat();
            $chat->create(['uid'=>$dataArr['uid'],'username'=>$dataArr['username']]);
            
            $chatUser = $chat->select()->toArray();
            
          $this->worker->uidConnections[$connection->uid] = $connection;
          
          //每次有新用户进来绑定了 那就将在线用户进行共享推送给在线用户
              	foreach ($this->worker->connections as $conns) {
                        $conns->send(
                            json_encode([
                              'info'=>$chatUser,
                              'adduser'=>['uid'=>$dataArr['uid'],'username'=>$dataArr['username']],
                              'type'=>"bind"
                              ])
                        );
                        
                    }
                    
            //加入Timer类 进行发送数据
                    // 每隔 5 秒向股票数据源发送请求更新股票数据
                    // $timer_id = Timer::add(5, function () use ($data_connection) {
                    //     $data_connection->send("update");
                    // });
                    // // 将定时器 ID 保存到连接对象中，以便在连接断开时清除定时器
                    // $connection->timer_id = $timer_id;
	           break;
	           case 'image':
	               	           	 //群聊图片
	           	 if($dataArr['toUserid'] == 'All'){
            
	           	   foreach ($this->worker->connections as $conns) {
                        $conns->send(
                            json_encode([
                                'type' => 'image',
                                'username' => $dataArr['username'],
                                'url' => $dataArr['url'],
                                'uid'=>$dataArr['uid'],
                            ])
                        );
                    }
                    return;
	           	 }
	               break;
	       case 'text':
	           	 //群聊
	           	 if($dataArr['toUserid'] == 'All'){
            
	           	   foreach ($this->worker->connections as $conns) {
                        $conns->send(
                            json_encode([
                                'type' => 'text',
                                'username' => $dataArr['username'],
                                'say' => $dataArr['say'],
                                'uid'=>$dataArr['uid'],
                                'headImage'=>$dataArr['headImage'],
                            ])
                        );
                    }
                    return;
	           	 }
	           	 
	           	 //私聊
	            if(isset($this->worker->uidConnections[$dataArr['toUserid']]))
                {
                    $conn = $this->worker->uidConnections[$dataArr['toUserid']];
                    $data = [
                        'username'=>$dataArr['username'],
                        'say'=>'【私】'.$dataArr['say'],
                        'type'=>'text',
                        'toUserid'=>$dataArr['toUserid'],
                        'headImage'=>$dataArr['headImage'],
                        ];
                    $conn->send(json_encode($data));
                }else{
                    echo "user不在线！:".$dataArr['toUserid'];
                }
	           break;
	           case "close886":
	               
	           	   foreach ($this->worker->connections as $conns) {
                        $conns->send(
                            json_encode([
                                'type' => 'close886',
                                'username' => $dataArr['username'],
                                'uid'=>$dataArr['uid'],
                            ])
                        );
                    }
                    
                    return;
	               
	               break;
	               //心跳检测
	               case 'ping':
	                 $connection->send(json_encode($dataArr));
	               break;
	       default:
	           // code...
	           break;
	   }
	    
	    
	    
	}
	
	//每个新用户进入只会触发一次
	public function onConnect($connection){
	   // var_dump($connection);
	}
	
	//心跳监听 如果客户端长时间没有发送信息给onmessage
	public function onWorkerStart($worker){
    	   // Timer::add(10, function()use($worker){
        //     $time_now = time();
        // 主动执行代码。。。消费队列也行
        //     foreach($this->worker->connections as $connection) {
        //         // 有可能该connection还没收到过消息，则lastMessageTime设置为当前时间
        //         if (empty($connection->lastMessageTime)) {
        //             $connection->lastMessageTime = $time_now;
        //             continue;
        //         }
        //         // 上次通讯时间间隔大于心跳间隔，则认为客户端已经下线，关闭连接
        //         if ($time_now - $connection->lastMessageTime > self::$timeHert) {
        //             $connection->close();
        //         }
        //     }
        // });
	}
	
	//客户单后关闭触发逻辑
	public function onClose($data)
	{
	    $dataArr = (array)$data;
        // $dataArr = json_decode($data,true);
        
        //  if(input('?id'))$id = input('id');
           
        //$result = MaterialM::destroy($id); //根据id主键删除 可id数组
        //删除在线列表
        $chat = new Chat();
        $chat->where(['uid'=>$dataArr['uid']])->delete();
	}
}