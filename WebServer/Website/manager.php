<?php
/**
 * Copyright 2017 Liming Jin
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

/**
 * Created by Liming
 * Date: 2017/2/6
 * Time: 18:07
 */
require_once __DIR__.'/../Workerman/Autoloader.php';
require __DIR__.'/../Template/constant.php';
use Database\Question;
use Database\User;
use MongoDB\BSON\ObjectID;

if(empty($_SESSION['user']->su) || !$_SESSION['user']->su) {
    header('Location: /', true, 301);
    die;
}

if(IS_POST) {
    if(empty($_GET['c'])) {
        header('Location: ?', true, 303);
        die;
    } elseif($_GET['c'] == 'user') {
        if(!empty($_POST['c'])) {
            switch($_POST['c']) {
                case 'ban_batch':
                    if(isset($_POST['ids']) && is_array($_POST['ids'])) {
                        unset($id);
                        foreach($_POST['ids'] as &$id) {
                            $id = new ObjectID($id);
                        }
                        unset($id);
                        User::getInstance()->modify_batch($_POST['ids'], ['ban' => true]);
                        User::getInstance()->modify_unset_batch($_POST['ids'], ['token']);
                    }
                    break;
                case 'ban':
                    if(isset($_POST['id'])) {
                        $_POST['id'] = new ObjectID($_POST['id']);
                        User::getInstance()->modify($_POST['id'], ['ban' => true]);
                        User::getInstance()->modify_unset($_POST['id'], ['token']);
                        header('Content-type: application/json');
                        echo json_encode(['code' => 0, 'message' => 'success']);
                        exit;
                    } else {
                        header('Content-type: application/json');
                        echo json_encode(['code' => -1, 'message' => 'error']);
                        exit;
                    }
                    break;
                case 'unban':
                    if(isset($_POST['id'])) {
                        $_POST['id'] = new ObjectID($_POST['id']);
                        User::getInstance()->modify_unset($_POST['id'], ['ban']);
                        header('Content-type: application/json');
                        echo json_encode(['code' => 0, 'message' => 'success']);
                        exit;
                    } else {
                        header('Content-type: application/json');
                        echo json_encode(['code' => -1, 'message' => 'error']);
                        exit;
                    }
                    break;
                case 'reset':
                    if(!empty($_POST['id']) && !empty($_POST['p'])) {
                        User::getInstance()->modifyPassword(new ObjectID($_POST['id']), $_POST['p']);
                        header('Content-type: application/json');
                        echo json_encode(['code' => 0, 'message' => 'success']);
                        exit;
                    } else {
                        header('Content-type: application/json');
                        echo json_encode(['code' => -1, 'message' => 'error']);
                        exit;
                    }
                    break;
            }
        }
    } elseif($_GET['c'] == 'question') {
        if(!empty($_POST['c'])) {
            switch($_POST['c']) {
                case 'delete':
                    if(!empty($_POST['id'])) {
                        Question::getInstance()->delete(new ObjectID($_POST['id']));
                    }
                    break;
            }
        }
    } else {
        header('Location: ?', true, 303);
        die;
    }
}

$page = empty($_GET['page']) ? 0 : intval($_GET['page']) - 1;
$pageSize = 100;
?>
<!DOCTYPE html>
<html lang="zh-cmn-Hans">
<head>
    <?php include '../Template/head.html'; ?>
    <link rel="stylesheet" href="/c/manager.css">
    <title>Manager - Online Judge</title>
</head>
<body>
<?php include '../Template/title.php'; ?>
<nav class="flex">
    <button id="btnUser">用户管理</button>
    <button id="btnQuestion">问题管理</button>
</nav>
<?php
if(!empty($_GET['c']) && $_GET['c'] == 'user') {
    $condition = [];
    if(!empty($_GET['search'])) {
        try {
            $condition = [
                '$or' => [
                    ['_id' => new ObjectID($_GET['search'])],
                    ['username' => $_GET['search']],
                    ['name' => $_GET['search']]
                ]
            ];
        } catch (InvalidArgumentException $e) {
            $condition = [
                '$or' => [
                    ['username' => $_GET['search']],
                    ['name' => $_GET['search']]
                ]
            ];
        }
    }

    $_maxPage = ceil(User::getInstance()->getCount($condition) / $pageSize);
    if($_maxPage > 0 && $page >= $_maxPage) {
        header('Location: ?c=user&page='.$_maxPage, true, 301);
        die;
    }

    $users = User::getInstance()->getList($condition, $page * $pageSize, $pageSize);
    ?>
<div id="control">
    <button id="btnBan">批量封禁选中用户</button>
    <input id="txtSearch" placeholder="查找 id/账号/用户名"<?= !empty($_GET['search']) ? ' value="'.$_GET['search'].'"' : '' ?>>
</div>
<table id="table">
    <tr>
        <th><input id="selectAll" title="全选" type="checkbox"></th>
        <th>id</th>
        <th>账号</th>
        <th>用户名</th>
        <th>通过率</th>
        <th>重置密码</th>
        <th>封禁</th>
    </tr>
    <?php
    foreach($users as $user) {
        if(isset($user->totalPass)) {
            $pass = $user->totalPass;
        } else {
            $pass = 0;
        }
        if(isset($user->totalSubmit)) {
            $submit = $user->totalSubmit;
        } else {
            $submit = 0;
        }
        ?>
    <tr>
        <td><input type="checkbox" data-id="<?= $user->_id ?>"></td>
        <td><?= $user->_id ?></td>
        <td><?= $user->username ?></td>
        <td><?= $user->name ?></td>
        <td><?= $submit == 0 ? 0 : round($pass / $submit * 100, 3) ?>%</td>
        <td><button data-id="<?= $user->_id ?>" data-c="reset">重置</button></td>
        <td><button data-id="<?= $user->_id ?>" data-c="<?= isset($user->ban) && $user->ban ? 'unban' : 'ban' ?>"><?= isset($user->ban) && $user->ban ? '解封' : '封禁' ?></button></td>
    </tr>
        <?php
    }
    ?>
</table>
<div class="pages">
    <?php
    if($page > 0) {
        ?>
    <a role="button" href="?page=<?= $page ?>">上一页</a>
        <?php
    }
    if($page < $_maxPage - 1) {
        ?>
    <a role="button" href="?page=<?= $page + 2 ?>">下一页</a>
        <?php
    }
    ?>
</div>
    <?php
} elseif(!empty($_GET['c']) && $_GET['c'] == 'question') {
    $condition = [];
    if(!empty($_GET['search'])) {
        try {
            $condition = [
                '$or' => [
                    ['_id' => new ObjectID($_GET['search'])],
                    ['title' => $_GET['search']],
                    ['adder' => $_GET['search']]
                ]
            ];
        } catch (InvalidArgumentException $e) {
            $condition = [
                '$or' => [
                    ['title' => $_GET['search']],
                    ['adder' => $_GET['search']]
                ]
            ];
        }
    }

    $_maxPage = ceil(Question::getInstance()->getCount($condition) / $pageSize);
    if($_maxPage > 0 && $page >= $_maxPage) {
        header('Location: ?c=user&page='.$_maxPage, true, 301);
        die;
    }

    $questions = Question::getInstance()->getList($condition, $page * $pageSize, $pageSize);
    ?>
<div id="control">
    <button id="btnInsert">新建</button>
    <input id="txtSearch" placeholder="查找 id/标题/添加者"<?= !empty($_GET['search']) ? ' value="'.$_GET['search'].'"' : '' ?>>
</div>
<table id="table">
    <tr>
        <th>id</th>
        <th>标题</th>
        <th>添加者</th>
        <th>添加时间</th>
        <th>修改</th>
        <th>测试用例</th>
        <th>删除</th>
    </tr>
    <?php
    foreach($questions as $question) {
        ?>
    <tr>
        <td><a href="/question.php?id=<?= $question->_id ?>" target="_blank"><?= $question->_id ?></a></td>
        <td><?= $question->title ?></td>
        <td><?= $question->adder ?></td>
        <td><?= date('Y-m-d H:i:s', $question->add_time / 1000) ?></td>
        <td><button data-id="<?= $question->_id ?>" data-c="modify">修改</button></td>
        <td><button data-id="<?= $question->_id ?>" data-c="test_case">查看</button></td>
        <td><button data-id="<?= $question->_id ?>" data-c="delete">删除</button></td>
    </tr>
        <?php
    }
    ?>
</table>
<div class="pages">
    <?php
    if($page > 0) {
        ?>
    <a role="button" href="?page=<?= $page ?>">上一页</a>
        <?php
    }
    if($page < $_maxPage - 1) {
        ?>
    <a role="button" href="?page=<?= $page + 2 ?>">下一页</a>
        <?php
    }
    ?>
</div>
    <?php
}
?>
<?php include '../Template/footer.html'; ?>
<script src="/j/websocket.js"></script>
<script src="/j/manager.js"></script>
</body>
</html>
