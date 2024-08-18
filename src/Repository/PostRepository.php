<?php

namespace App\Repository;

use App\Entity\Post;
use App\Filter\PostFilter;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Post>
 */
class PostRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Post::class);
    }

    public function findByFilter(PostFilter $postFilter)
    {
        $posts = $this
            ->createQueryBuilder("p")
            ->join("p.author", "a");
        if ($postFilter->title) {
            $posts
                ->andWhere("p.title LIKE :title")
                ->setParameter("title", "%" . $postFilter->title . "%");
        }
        if ($postFilter->authorEmail) {
            $posts
                ->andWhere("a.email LIKE :email")
                ->setParameter("email", "%" . $postFilter->authorEmail . "%");
        }
        if ($postFilter->content) {
            $posts->andWhere("p.content LIKE :content")
                ->setParameter("content", "%" . $postFilter->content . "%");
        }
        return $posts;
    }
}
