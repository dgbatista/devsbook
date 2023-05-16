<?php
require_once 'models/Post.php';
require_once 'dao/UserRelationDaoMySql.php';
require_once 'dao/UserDaoMySql.php';
require_once 'dao/PostLikeDaoMySql.php';
require_once 'dao/PostCommentDaoMySql.php';

class PostDaoMySql implements PostDAO {
    private $pdo;

    public function __construct(PDO $driver){
        $this->pdo = $driver;
    }

    public function insert(Post $p){
        $sql = $this->pdo->prepare('INSERT INTO posts (
            id_user, type, created_at, body
        ) VALUES (
            :id_user, :type, :created_at, :body
        )');
        $sql->bindValue(':id_user', $p->id_user);
        $sql->bindValue(':type', $p->type);
        $sql->bindValue(':created_at', $p->created_at);
        $sql->bindValue(':body', $p->body);
        $sql->execute();

        return true;
    }

    public function delete($id, $id_user){
        $postLikeDao = new PostLikeDaoMySql($this->pdo);
        $postCommentDao = new PostCommentDaoMySql($this->pdo);

        //1 - verificar se o post existe (o tipo)
        $sql = $this->pdo->prepare("SELECT * FROM posts WHERE id = :id AND id_user = :id_user");
        $sql->bindValue(":id", $id);
        $sql->bindValue(":id_user", $id_user);
        $sql->execute();

        if($sql->rowCount() > 0){
            $post = $sql->fetch(PDO::FETCH_ASSOC);
            //2 - deletar os likes e coments   
            $postLikeDao->deleteFromPost($id);
            $postCommentDao->deleteFromPost($id);

            //3 - deletar o eventual foto (type == photo);
            if($post['type'] === 'photo'){
                $img = 'media/uploads/'.$post['body'];
                if(file_exists($img)){
                    unlink($img); //deleta o arquivo
                }
            }

            //4 - deletar o post
            $sql = $this->pdo->prepare("DELETE FROM posts WHERE id = :id AND id_user = :id_user");
            $sql->bindValue(":id", $id);
            $sql->bindValue(":id_user", $id_user);
            $sql->execute();       

            return true;
        }
    }

    public function getUserFeed($id_user){
        $array = [];
        
        $sql = $this->pdo->prepare("SELECT * FROM posts WHERE id_user = :id_user 
        ORDER BY created_at DESC");
        $sql->bindValue(':id_user', $id_user);
        $sql->execute();

        if($sql->rowCount() > 0) {
            $data = $sql->fetchAll(PDO::FETCH_ASSOC);
            $array = $this->_postListToObject($data, $id_user);
        }
        
        return $array;
    }

    public function getHomeFeed($id_user){
        $array = [];
        //1 - Lista dos usuários que Eu sigo.
        $urDao = new UserRelationDaoMySql($this->pdo);
        $userList = $urDao->getFollowing($id_user);
        $userList[] = $id_user;

        //2 - Pegar os posts da lista 1, ordenado pela data
        $sql = $this->pdo->query("SELECT * FROM posts WHERE id_user IN (".implode(',', $userList).")
        ORDER BY created_at DESC");
        if($sql->rowCount() > 0) {
            $data = $sql->fetchAll(PDO::FETCH_ASSOC);
            
            //3 - Transformar o resultado em objetos
            $array = $this->_postListToObject($data, $id_user);
        }

        
        return $array;
    }

    public function getPhotosFrom($id_user){
        $array = [];

        $sql = $this->pdo->prepare("SELECT * FROM posts 
        WHERE id_user = :id_user AND type = 'photo' 
        ORDER BY created_at DESC");
        $sql->bindValue(':id_user', $id_user);
        $sql->execute();

        if($sql->rowCount() > 0) {
            $data = $sql->fetchAll(PDO::FETCH_ASSOC);
            
            //Transformar a lista em objetos e retorna ela
            $array = $this->_postListToObject($data, $id_user);
        }

        return $array;
    }

    private function _postListToObject($post_list, $id_user) {
        $posts = [];
        $userDao = new UserDaoMySql($this->pdo);
        $postLikeDao = new PostLikeDaoMySql($this->pdo);
        $postCommentDao = new PostCommentDaoMySql($this->pdo);

        foreach($post_list as $post_item){
            $newPost = new Post();
            $newPost->id = $post_item['id'];
            $newPost->type = $post_item['type'];
            $newPost->created_at = $post_item['created_at'];
            $newPost->body = $post_item['body'];
            $newPost->mine = false;

            if($post_item['id_user'] == $id_user){
                $newPost->mine = true;
            }

            //Pegar informações do usuario
            $newPost->user = $userDao->findById($post_item['id_user']);

            // Informações sobre like
            $newPost->likeCount = $postLikeDao->getLikeCount($newPost->id);
            $newPost->liked = $postLikeDao->isLiked($newPost->id, $id_user);

            //Informações sobre COMMENTS
            $newPost->comments = $postCommentDao->getComment($newPost->id);

            $posts[] = $newPost;

        }

        return $posts;
    }
}