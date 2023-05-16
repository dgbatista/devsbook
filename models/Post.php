<?php

class Post {
    public $id;
    public $id_user;
    public $type; //text ou foto
    public $created_at;
    public $body;
    public $city;
}

interface PostDAO {
    public function insert(Post $p);
    public function delete($id, $id_user);
    public function getHomeFeed($id_user);
    public function getUserFeed($id_user);
    public function getPhotosFrom($id_user);
}