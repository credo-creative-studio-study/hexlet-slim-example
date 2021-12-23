<?php

// Подключение автозагрузки через composer
require __DIR__ . '/../vendor/autoload.php';

use Slim\Factory\AppFactory;
use Slim\Middleware\MethodOverrideMiddleware;
use DI\Container;
use App\Validator;

session_start();

if (!isset($_SESSION['user'])) {
    $_SESSION['user'] = [
        'isAuthenticated' => false,
        'name' => '',
        'password' => ''
    ];
}


$container = new Container();
$container->set('renderer', function () {
    return new \Slim\Views\PhpRenderer(__DIR__ . '/../templates');
});

$container->set('flash', function () {
    return new \Slim\Flash\Messages();
});

AppFactory::setContainer($container);
$app = AppFactory::create();
$app->add(MethodOverrideMiddleware::class);

// $users = json_decode(file_get_contents('./DB/db.json'), true);
$session_users = [
    ['name' => 'admin', 'passwordDigest' => hash('sha256', 'secret')],
    ['name' => 'mike', 'passwordDigest' => hash('sha256', 'superpass')],
    ['name' => 'kate', 'passwordDigest' => hash('sha256', 'strongpass')]
];

$router = $app->getRouteCollector()->getRouteParser();

$app->get('/', function ($req, $res) use (&$users1) {
    $flash = $this->get('flash')->getMessages();
    $params = [
        'user' => $_SESSION['user'],
        'flash' => $flash
    ];
    return $this->get('renderer')->render($res, "index.phtml", $params);
})->setName('home');

$app->post('/session', function ($req, $res) use ($session_users, $router) {
    $data = $req->getParsedBodyParam('user');
    $user = array_values(array_filter($session_users, fn($user) => $user['name'] == $data['name']))[0];
    $userIsExist = empty($user) ? false : true;
    $url = $router->urlFor('home');
    if ($userIsExist) {
        if ($user['passwordDigest'] === hash('sha256', $data['password'])) {
            $_SESSION['user']['isAuthenticated'] = true;
            $_SESSION['user']['name'] = $user['name'];
            $_SESSION['user']['password'] = $user['password'];

            $this->get('flash')->addMessage('success', 'Success authenticated');
            return $res->withRedirect($url, 302);
        } else {
            $this->get('flash')->addMessage('error', 'Wrong password or name');
            return $res->withRedirect($url, 422);
        }
    }
    $this->get('flash')->addMessage('error', 'User not found');
    return $res->withRedirect($url, 404);
});

$app->delete('/session', function ($req, $res) use ($router) {
    $_SESSION['user'] = [];
    session_destroy();
    $url = $router->urlFor('home');
    return $res->withRedirect($url, 302);
});

$app->get('/users', function ($req, $res) use ($users, $router) {
    $users1 = json_decode($req->getCookieParam('users', json_encode([])), true);
    $page = $req->getQueryParam('page', 1);
    $per = 5;
    $offset = ($page - 1) * $per;

    // $users = json_decode(file_get_contents('./DB/db.json'), true);
    // $pagedUsers = array_slice($users, $offset, $per);
    $pagedUsers = array_slice($users1, $offset, $per);
    // print_r($pagedUsers);
    $flash = $this->get('flash')->getMessages();
    $params = [
        'users' => $pagedUsers,
        'flash' => $flash,
        'router' => $router,
        'pagination' => [
            'page' => $page,
            'total_pages' => ceil(count($users1) / $per)
            // 'total_pages' => ceil(count($users) / $per)
        ]
    ];
    return $this->get('renderer')->render($res, "users/index.phtml", $params);
})->setName('users');

$app->get('/users/new', function ($req, $res) {
    $params = [
        'user' => [
            'id' => '',
            'nickname' => '',
            'email' => ''
        ]
    ];
    return $this->get('renderer')->render($res, 'users/new.phtml', $params);
})->setName('users-new');


$app->post('/users', function ($req, $res) use (&$users, $router) {
    $user = $req->getParsedBodyParam('user');
    $users1 = json_decode($req->getCookieParam('users', json_encode([])), true);
    // $id = $users[count($users) - 1] ? $users[count($users) - 1]['id'] + 1 : 1;
    $id = $users1[count($users1) - 1] ? $users1[count($users1) - 1]['id'] + 1 : 1;
    $user['id'] = $id;
    $validator = new Validator();
    $errors = $validator->validate($user);
    // print_r($user);
    if (count($errors) === 0) {
        // $users[] = $user;
        $users1[] = $user;
        $encodedUsers = json_encode($users1);
        print_r($encodedUsers);
        // file_put_contents('./DB/db.json', json_encode($users));
        $this->get('flash')->addMessage('success', 'User added');
        // return $res->withRedirect($router->urlFor('users'), 302);
        return $res->withHeader('Set-Cookie', "users={$encodedUsers}")
                    ->withRedirect($router->urlFor('users'), 302);
    }

    $params = [
        'user' => $user,
        'errors' => $errors
    ];
    $res = $res->withStatus(422);
    return $this->get('renderer')->render($res, 'users/new.phtml', $params);
})->setName('users-post');

$app->get('/users/{id}', function ($req, $res, array $args) use ($users, $router) {
    // $user = array_values(array_filter($users, fn($user) => $user['id'] == $args['id']))[0];
    $users1 = json_decode($req->getCookieParam('users', json_encode([])), true);
    $user1 = array_values(array_filter($users1, fn($user) => $user['id'] == $args['id']))[0];
    // if (!$user) {
    if (!empty($user1)) {
        $params = [
            // 'user' => $user
            'user' => $user1
        ];
        return $this->get('renderer')->render($res, 'users/show.phtml', $params);
    } else {
        return $res->withRedirect($router->urlFor('users'), 404);
    }
})->setName('users-show');


$app->get('/users/{id}/edit', function ($req, $res, array $args) use ($users, $router) {
    // $user = array_values(array_filter($users, fn($user) => $user['id'] == $args['id']))[0];
    $users1 = json_decode($req->getCookieParam('users', json_encode([])), true);
    $user1 = array_values(array_filter($users1, fn($user) => $user['id'] == $args['id']))[0];
    $flash = $this->get('flash')->getMessages();
    $params = [
        // 'user' => $user,
        'user' => $user1,
        'flash' => $flash,
        'errors' => []
    ];
    return $this->get('renderer')->render($res, 'users/edit.phtml', $params);
})->setName('users-edit');

$app->patch('/users/{id}', function ($req, $res, array $args) use (&$users, $router) {
    // $user = array_values(array_filter($users, fn($user) => $user['id'] == $args['id']))[0];
    $users1 = json_decode($req->getCookieParam('users', json_encode([])), true);
    $user1 = array_values(array_filter($users1, fn($user) => $user['id'] == $args['id']))[0];
    $data = $req->getParsedBodyParam('user');
    $validator = new App\Validator();
    $errors = $validator->validate($data);
    if (count($errors) === 0) {
        // $user['nickname'] = $data['nickname'];
        // $user['email'] = $data['email'];

        $user1['nickname'] = $data['nickname'];
        $user1['email'] = $data['email'];

        // $users = array_map(function ($oldUser) use ($user) {
        //     if ($oldUser['id'] == $user['id']) {
        //         return $user;
        //     }

        //     return $oldUser;
        // }, $users);

        $users1 = array_map(function ($oldUser) use ($user1) {
            if ($oldUser['id'] == $user1['id']) {
                return $user1;
            }
            return $oldUser;
        }, $users1);


        // file_put_contents('./DB/db.json', json_encode($users));
        $this->get('flash')->addMessage('success', 'User has been updated');
        // return $res->withRedirect($router->urlFor('users-edit', ['id' => $args['id']]), 302);
        $encodedUsers = json_encode($users1);
        return $res->withHeader('Set-Cookie', "users={$encodedUsers}")
                    ->withRedirect($router->urlFor('users-edit', ['id' => $args['id']]), 302);
    }

    $params = [
        'user' => $data,
        'errors' => $errors
    ];
    $res = $res->withStatus(422);
    return $this->get('renderer')->render($res, 'users/edit.phtml', $params);
});


$app->delete('/users/{id}', function ($req, $res, array $args) use ($router, $users) {
    $id = $args['id'];
    // $users = array_values(array_filter($users, fn($user) => $user['id'] != $id));
    $users1 = json_decode($req->getCookieParam('users', json_encode([])), true);
    echo '<pre>';
    print_r($users1);
    echo '</pre>';
    $users1 = array_values(array_filter($users1, fn($user) => $user['id'] != $id));
    echo '<pre>';
    print_r($users1);
    echo '</pre>';
    $encodedUsers = json_encode($users1);
    // file_put_contents('./DB/db.json', json_encode($users));
    $this->get('flash')->addMessage('success', 'User has been deleted');
    $url = $router->urlFor('users');
    // return $res->withRedirect($url, 302);
    return $res->withHeader('Set-Cookie', "users={$encodedUsers}")
                ->withRedirect($url, 302);
});



$app->run();
