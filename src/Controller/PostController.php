<?php

namespace App\Controller;

use App\Entity\Post;
use App\Filter\PostFilter;
use App\Repository\PostRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

#[Route('/api/post', defaults: ["_format" => "json"])]
class PostController extends AbstractController
{
    public function __construct(private PostRepository $postRepository, #[Autowire('%kernel.project_dir%/public/uploads/post/images')] private string $imagesDirectory)
    {
    }
    #[Route('/', name: 'app_post', methods: "GET")]
    public function index(#[MapQueryString] PostFilter $postFilter, Request $request): Response
    {
        $page = $request->query->getInt("page", 1);
        $maxResults = $request->query->getInt("maxResults", 10);
        $posts = $this->postRepository->findByFilter($postFilter);
        $totalPosts = count($posts->getQuery()->getResult());
        $posts = $posts
            ->setFirstResult($maxResults * ($page - 1))
            ->setMaxResults($maxResults)->getQuery()->getResult();
        return $this->json([
            "posts" => $posts,
            "total" => $totalPosts,
            "page" => $page,
            "pages" => ceil($totalPosts / $maxResults),
        ]);
    }
    #[Route('/create', name: 'app_post_create', methods: "POST")]
    public function create(
        Request $request,
        EntityManagerInterface $em,
        SluggerInterface $slugger,
        SerializerInterface $serializer,
        ValidatorInterface $validator,
    ) {
        $data = $request->request->all();
        $post = new Post();
        $postImage = $request->files->get('image');
        if (!$postImage) {
            return $this->json(["errors" => ["image" => "Image is required."]], Response::HTTP_NO_CONTENT);
        }
        $safeFileName = $slugger->slug(pathinfo($postImage->getClientOriginalName(), PATHINFO_FILENAME));
        $newFileName = $safeFileName . "-" . uniqid() . "." . $postImage->guessExtension();
        $post
            ->setTitle($data["title"])
            ->setAuthor($this->getUser())
            ->setContent($data["content"])
            ->setImageFilename($newFileName);
        $errors = $validator->validate($post);
        if (count($errors) > 0) {
            $messages["errors"] = [];
            foreach ($errors as $error) {
                $messages["errors"][$error->getPropertyPath()] = $error->getMessage();
            }
            return $this->json($messages, Response::HTTP_BAD_REQUEST);
        }
        try {
            $em->beginTransaction();
            $em->persist($post);
            $em->flush();
            $postImage->move($this->imagesDirectory, $newFileName);
            $em->commit();
            $postJson = $serializer->serialize($post, 'json', context: [
                'circular_reference_handler' => function ($object) {
                    return $object->getId();
                },
            ]);
            return $this->json(["post" => json_decode($postJson)]);
        } catch (\Exception $e) {
            $em->rollback();
            $filesystem = new Filesystem();
            if ($filesystem->exists($this->imagesDirectory . '/' . $newFileName)) {
                $filesystem->remove($this->imagesDirectory . '/' . $newFileName);
            }
            return throw $e;
        }
    }
    #[Route('/delete/{postId}', name: 'app_post_delete', methods: "DELETE")]
    public function delete(int $postId, EntityManagerInterface $em)
    {
        $post = $this->postRepository->findOneBy(['id' => $postId]);
        if (!$post) {
            return $this->json(["error" => "Post not found."], Response::HTTP_NOT_FOUND);
        }
        if ($this->getUser() !== $post->getAuthor()) {
            return $this->json(["error" => "Access denied."], Response::HTTP_FORBIDDEN);
        }
        $filesystem = new Filesystem();
        if ($filesystem->exists($this->imagesDirectory . '/' . $post->getImageFilename())) {
            $filesystem->remove($this->imagesDirectory . '/' . $post->getImageFilename());
        }
        $em->remove($post);
        $em->flush();
        return $this->json(["message" => "Post was deleted successfuly."], Response::HTTP_OK);

    }
}
