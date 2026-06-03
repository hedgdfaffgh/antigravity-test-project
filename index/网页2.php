<?php
session_start();

// 生成并存储唯一令牌
if (!isset($_SESSION['token'])) {
    $_SESSION['token'] = bin2hex(random_bytes(32));
}
$token = $_SESSION['token'];

// 检查用户是否已经通过了验证码验证
if (!isset($_SESSION['captcha_verified']) || !$_SESSION['captcha_verified']) {
    header('Location: https://' . getenv('BASE_URL') . '/qlrucaptcha.php');
    exit();
}

// 设置用户已访问网页2的会话变量
$_SESSION['has_visited_webpage2'] = true;

// 获取 parent_id
$parent_id = $_SESSION['parent_id'];

// 检查 parent_id 是否为父账号
$is_parent = ($parent_id === '0');

$base_url = getenv('BASE_URL');
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<title>示例页面</title>
<style>
  .button-container {
    position: fixed;
    top: 50%;
    left: 10px;
    transform: translateY(-50%);
  }

  .button {
    display: block;
    margin: 5px 0;
    padding: 10px 20px;
    background-color: #f2f2f2;
    border: 1px solid #dcdcdc;
    cursor: pointer;
    font-size: 16px;
    border-radius: 5px;
    transition: background-color 0.3s, border-color 0.3s;
    text-align: center;
  }

  .button:hover {
    background-color: #e9e9e9;
    border-color: #c6c6c6;
  }

  .vertical-line {
    position: fixed;
    top: 0;
    left: calc(100px);
    width: 2px;
    height: 100vh;
    background-color: #000;
  }

  #data-iframe {
    position: fixed;
    top: 0;
    left: 102px;
    width: calc(100% - 102px);
    height: 100vh;
    border: none;
    display: none;
  }
</style>
</head>
<body>

<div class="vertical-line"></div>

<div class="button-container">
  <?php if ($is_parent): ?>
    <button class="button" id="file-button">文件</button>
  <?php endif; ?>
  <button class="button" id="user-button">用户</button>
  <button class="button" id="key-button">密钥</button> <!-- 新增密钥按钮 -->
  <button class="button" id="channel-button">通道</button> <!-- 新增通道按钮 -->
  <button class="button" id="number-button">号码</button>
  <button class="button" id="account-button">账号</button> <!-- 新增账号按钮 -->
  <button class="button" id="order-button">订单</button>
  <button class="button" id="load-data">未调</button>
  <button class="button" id="data-button">数据</button> 
  <button class="button" id="kami-button">卡密</button> 
  <button class="button" id="website-button">网址</button> 
  <button class="button" id="gold-limit-button">金限</button> 
</div>

<iframe id="data-iframe"></iframe>

<script>
window.onload = function() {
  var dataIframe = document.getElementById('data-iframe');
  var savedSrc = localStorage.getItem('iframeSrc');
  if (savedSrc) {
    dataIframe.src = savedSrc;
    dataIframe.style.display = 'block';
  }

  var buttons = document.querySelectorAll('.button');
  buttons.forEach(function(button) {
    button.addEventListener('click', function() {
      buttons.forEach(function(btn) { btn.style.backgroundColor = '#f2f2f2'; });
      this.style.backgroundColor = 'blue';
      var src = '';
      switch (this.id) {
        case 'file-button':
          src = 'https://' + '<?php echo $base_url; ?>' + '/002.php?parent_id=' + '<?php echo $parent_id; ?>&token=<?php echo $token; ?>';
          break;
        case 'user-button':
          src = 'https://' + '<?php echo $base_url; ?>' + '/iymm.php?parent_id=' + '<?php echo $parent_id; ?>&token=<?php echo $token; ?>';
          break;
        case 'number-button':
          src = 'https://' + '<?php echo $base_url; ?>' + '/qlru002.php?parent_id=' + '<?php echo $parent_id; ?>&token=<?php echo $token; ?>';
          break;
        case 'order-button':
          src = 'https://' + '<?php echo $base_url; ?>' + '/数据.php?parent_id=' + '<?php echo $parent_id; ?>&token=<?php echo $token; ?>';
          break;
        case 'load-data':
          src = 'https://' + '<?php echo $base_url; ?>' + '/003.php?parent_id=' + '<?php echo $parent_id; ?>&token=<?php echo $token; ?>';
          break;
        case 'data-button':
          src = 'https://' + '<?php echo $base_url; ?>' + '/qlru.php?token=<?php echo $token; ?>';
          break;
        case 'kami-button':
          src = 'https://' + '<?php echo $base_url; ?>' + '/qlrurjbc.php?token=<?php echo $token; ?>';
          break;
        case 'website-button':
          src = 'https://' + '<?php echo $base_url; ?>' + '/qlru001.php?token=<?php echo $token; ?>';
          break;
        case 'gold-limit-button':
          src = 'https://' + '<?php echo $base_url; ?>' + '/qlru003.php?token=<?php echo $token; ?>';
          break;
        case 'key-button': // 新增代码
          src = 'https://' + '<?php echo $base_url; ?>' + '/qlrupnqe.php?token=<?php echo $token; ?>';
          break;
        case 'channel-button': // 新增代码
          src = 'https://' + '<?php echo $base_url; ?>' + '/qlruceut.php?token=<?php echo $token; ?>';
          break;
        case 'account-button':
          src = 'https://' + '<?php echo $base_url; ?>' + '/qlrusyqitkg.php?parent_id=' + '<?php echo $parent_id; ?>&token=<?php echo $token; ?>';
          break;
      }
      dataIframe.src = src;
      dataIframe.style.display = 'block';
      localStorage.setItem('iframeSrc', src);
    });
  });

  // 新增代码
  var fileButton = document.getElementById('file-button'); // 获取文件按钮
  var numberButton = document.getElementById('number-button'); // 获取号码按钮
  var userButton = document.getElementById('user-button'); // 获取用户按钮
  var goldLimitButton = document.getElementById('gold-limit-button'); // 获取金限按钮
  var keyButton = document.getElementById('key-button'); // 获取密钥按钮
  var channelButton = document.getElementById('channel-button'); // 获取通道按钮
  var accountButton = document.getElementById('account-button'); // 获取账号按钮

  // 为文件按钮添加点击事件处理程序
  if (fileButton) {
    fileButton.addEventListener('click', function() {
      var src = 'https://' + '<?php echo $base_url; ?>' + '/002.php?token=<?php echo $token; ?>';
      dataIframe.src = src;
      dataIframe.style.display = 'block';
      localStorage.setItem('iframeSrc', src); // 保存URL到localStorage
    });
  }

  // 为号码按钮添加点击事件处理程序
  numberButton.addEventListener('click', function() {
    var src = 'https://' + '<?php echo $base_url; ?>' + '/qlru002.php?token=<?php echo $token; ?>';
    dataIframe.src = src;
    dataIframe.style.display = 'block';
    localStorage.setItem('iframeSrc', src); // 保存URL到localStorage
  });

  // 为用户按钮添加点击事件处理程序
  if (userButton) {
    userButton.addEventListener('click', function() {
      var src = 'https://' + '<?php echo $base_url; ?>' + '/iymm.php?token=<?php echo $token; ?>';
      dataIframe.src = src;
      dataIframe.style.display = 'block';
      localStorage.setItem('iframeSrc', src); // 保存URL到localStorage
    });
  }

  // 为金限按钮添加点击事件处理程序
  goldLimitButton.addEventListener('click', function() {
    var src = 'https://' + '<?php echo $base_url; ?>' + '/qlru003.php?token=<?php echo $token; ?>';
    dataIframe.src = src;
    dataIframe.style.display = 'block';
    localStorage.setItem('iframeSrc', src); // 保存URL到localStorage
  });

  // 为密钥按钮添加点击事件处理程序
  keyButton.addEventListener('click', function() {
    var src = 'https://' + '<?php echo $base_url; ?>' + '/qlrupnqe.php?token=<?php echo $token; ?>';
    dataIframe.src = src;
    dataIframe.style.display = 'block';
    localStorage.setItem('iframeSrc', src); // 保存URL到localStorage
  });

  // 为通道按钮添加点击事件处理程序
  channelButton.addEventListener('click', function() {
    var src = 'https://' + '<?php echo $base_url; ?>' + '/qlruceut.php?token=<?php echo $token; ?>';
    dataIframe.src = src;
    dataIframe.style.display = 'block';
    localStorage.setItem('iframeSrc', src); // 保存URL到localStorage
  });

  // 为账号按钮添加点击事件处理程序
  accountButton.addEventListener('click', function() {
    var src = 'https://' + '<?php echo $base_url; ?>' + '/qlrusyqitkg.php?token=<?php echo $token; ?>';
    dataIframe.src = src;
    dataIframe.style.display = 'block';
    localStorage.setItem('iframeSrc', src); // 保存URL到localStorage
  });
};

// 现有的为"未调"和"订单"按钮添加点击事件处理程序的代码保持不变
var loadDataButton = document.getElementById('load-data');
var orderButton = document.getElementById('order-button');
var dataIframe = document.getElementById('data-iframe');

loadDataButton.addEventListener('click', function() {
  var src = 'https://' + '<?php echo $base_url; ?>' + '/003.php?token=<?php echo $token; ?>';
  dataIframe.src = src;
  dataIframe.style.display = 'block';
  localStorage.setItem('iframeSrc', src);
});

orderButton.addEventListener('click', function() {
  var src = 'https://' + '<?php echo $base_url; ?>' + '/数据.php?token=<?php echo $token; ?>';
  dataIframe.src = src;
  dataIframe.style.display = 'block';
  localStorage.setItem('iframeSrc', src);
});
</script>
</body>
</html>