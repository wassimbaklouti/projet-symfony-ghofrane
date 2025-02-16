<?php

namespace App\Controller;

use App\Entity\Post;
use App\Entity\Comment;
use App\Entity\Like;
use App\Form\PostType;
use App\Form\CommentType;
use App\Repository\PostRepository;
use App\Repository\LikeRepository;
use App\Repository\CommentRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/post')]
class PostController extends AbstractController
{
    #[Route('/', name: 'app_post_index', methods: ['GET'])]
    public function index(PostRepository $postRepository): Response
    {
        return $this->render('post/index.html.twig', [
            'posts' => $postRepository->findAll(),
        ]);
    }

    #[Route('/front', name: 'app_post_front', methods: ['GET'])]
    public function front(PostRepository $postRepository): Response
    {
        return $this->render('post/index-front.html.twig', [
            'posts' => $postRepository->findAll(),
        ]);
    }

    #[Route('/new', name: 'app_post_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $post = new Post();
        $form = $this->createForm(PostType::class, $post);
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            if ($form->isValid()) {
                // Set current date
                $post->setDate(new \DateTime());

                /** @var UploadedFile|null $imageFile */
                $imageFile = $form->get('imageFile')->getData();

                if ($imageFile) {
                    $newFilename = uniqid() . '.' . $imageFile->guessExtension();

                    try {
                        $imageFile->move(
                            $this->getParameter('post_images_directory'),
                            $newFilename
                        );
                        $post->setImage($newFilename);
                    } catch (FileException $e) {
                        $this->addFlash('error', 'Failed to upload image: ' . $e->getMessage());
                        return $this->redirectToRoute('app_post_new');
                    }
                }

                $entityManager->persist($post);
                $entityManager->flush();

                $this->addFlash('success', 'Post added successfully!');
                return $this->redirectToRoute('app_post_index');
            } else {
                $this->addFlash('error', 'Form validation failed. Please check your input.');
            }
        }

        return $this->render('post/new.html.twig', [
            'post' => $post,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/front/{id}', name: 'app_post_show_front', methods: ['GET', 'POST'])]
    public function showFront(Post $post, CommentRepository $commentRepository, Request $request, EntityManagerInterface $entityManager): Response
    {
        // Create a new comment entity
        $comment = new Comment();
        $commentForm = $this->createForm(CommentType::class, $comment);
        
        // Handle form submission
        $commentForm->handleRequest($request);
        if ($commentForm->isSubmitted() && $commentForm->isValid()) {
            $comment->setDate(new \DateTime());
            $comment->setPost($post);
            $comment->setDate(new \DateTime());

            $entityManager->persist($comment);
            $entityManager->flush();

            return $this->redirectToRoute('app_post_show_front', ['id' => $post->getId()]);
        }

        return $this->render('post/show-front.html.twig', [
            'post' => $post,
            'comments' => $commentRepository->findBy(['post' => $post]),
            'commentForm' => $commentForm->createView(),
        ]);
    }

    #[Route('/{id}', name: 'app_post_show', methods: ['GET'])]
    public function show(Post $post, CommentRepository $commentRepository): Response
    {
        return $this->render('post/show.html.twig', [
            'post' => $post,
            'comments' => $commentRepository->findBy(['post' => $post], ['date' => 'DESC']),
        ]);
    }

    

    #[Route('/{id}/like', name: 'app_post_like', methods: ['POST'])]
    public function like(Post $post, EntityManagerInterface $entityManager, Request $request): Response
    {
        $session = $request->getSession();
        $likedPosts = $session->get('liked_posts', []);

        // If already liked, remove the like
        if (in_array($post->getId(), $likedPosts)) {
            $like = $entityManager->getRepository(Like::class)->findOneBy(['post' => $post]);

            if ($like) {
                $entityManager->remove($like);
                $entityManager->flush();
            }

            // Remove from session
            $likedPosts = array_diff($likedPosts, [$post->getId()]);
            $session->set('liked_posts', $likedPosts);

            $this->addFlash('success', 'You unliked this post.');
        } else {
            // Add new like
            $like = new Like();
            $like->setPost($post);
            $entityManager->persist($like);
            $entityManager->flush();

            // Store in session to track likes
            $likedPosts[] = $post->getId();
            $session->set('liked_posts', $likedPosts);

            $this->addFlash('success', 'You liked this post.');
        }

        return $this->redirect($request->headers->get('referer'));
    }




    #[Route('/{id}/edit', name: 'app_post_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Post $post, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(PostType::class, $post);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();
            return $this->redirectToRoute('app_post_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->renderForm('post/edit.html.twig', [
            'post' => $post,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_post_delete', methods: ['POST'])]
    public function delete(Request $request, Post $post, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete' . $post->getId(), $request->request->get('_token'))) {
            $entityManager->remove($post);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_post_index', [], Response::HTTP_SEE_OTHER);
    }
}
