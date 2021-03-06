<?php

namespace AppBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use \Doctrine\Common\Persistence\ObjectRepository;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\QueryBuilder;
use AppBundle\Entity\BlogPost;
use Symfony\Component\HttpFoundation\Response;

class BlogController extends Controller
{

    /**
     * @var ObjectRepository
     */
    protected $blogPostRepository;

    /**
     * @var ObjectRepository
     */
    protected $subjectRepository;

    /**
     * @var EntityManager
     */
    protected $em;
    /**
     * @var QueryBuilder
     */
    protected $builder;
    /**
     * @var BlogPost
     */
    protected $blogPost;

    /**
     * @param Request $request
     * @param $subjectId
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function listAction(Request $request, $subjectId)
    {
        $em = $this->get('doctrine.orm.entity_manager');
        $dql = "SELECT a FROM AppBundle:BlogPost a WHERE a.subject=" . $subjectId . " ORDER BY a.createTime DESC";
        $query = $em->createQuery($dql);

        $this->subjectRepository = $this->getDoctrine()->getRepository('AppBundle:Subject');
        $subject = $this->subjectRepository->find($subjectId);

        $paginator = $this->get('knp_paginator');
        $pagination = $paginator->paginate(
            $query,
            $request->query->get('page', 1)/*page number*/,
            10/*limit per page*/
        );

        return $this->render('blog/list.html.twig', array('pagination' => $pagination,
            'subject' => $subject,
            'latestblogs' => BlogController::getLatestBlogs($this),
            'tophotblogs' => BlogController::getTopHotBlogs($this)));
    }

    public function listbytagAction(Request $request)
    {
        $tagName = $request->get('tagname');
        $this->em = $this->get('doctrine.orm.entity_manager');
        $this->builder = $this->em->createQueryBuilder();
        $query = $this->builder->select('b')
            ->add('from', 'AppBundle:BlogPost b INNER JOIN b.tags t')
            ->where('t.name=:tag_name')
            ->setParameter('tag_name', $tagName)
            ->getQuery();

        $paginator = $this->get('knp_paginator');
        $pagination = $paginator->paginate(
            $query,
            $request->query->get('page', 1)/*page number*/,
            100/*limit per page*/
        );

        return $this->render('blog/listbytag.html.twig', array('pagination' => $pagination,
            'tagname' => $tagName,
            'latestblogs' => BlogController::getLatestBlogs($this),
            'tophotblogs' => BlogController::getTopHotBlogs($this)));
    }

    private function getAllTagNames()
    {
        $blogPostRepository = $this->getDoctrine()->getRepository('AppBundle:Tag');
        $tags = $blogPostRepository->findAll();
        return $tags;
    }

    public function showAction(Request $request)
    {
        $blogId = $request->get('blogId');
        $this->blogPostRepository = $this->getDoctrine()->getRepository('AppBundle:BlogPost');
        $this->blogPost = $this->blogPostRepository->find($blogId);
        $tags = $this->blogPost->getTags();
        $pagination = null;
        if (!empty($tags) && sizeof($tags) > 0) {
            $tag = $tags[0];
            $tagName = $tag->getName();
            $this->em = $this->get('doctrine.orm.entity_manager');
            $this->builder = $this->em->createQueryBuilder();
            $query = $this->builder->select('b')
                ->add('from', 'AppBundle:BlogPost b INNER JOIN b.tags t')
                ->where('t.name=:tag_name')
                ->setParameter('tag_name', $tagName)
                ->getQuery();

            $paginator = $this->get('knp_paginator');
            $pagination = $paginator->paginate(
                $query,
                $request->query->get('page', 1)/*page number*/,
                100/*limit per page*/
            );
        }

        if (!empty($this->blogPost)) {
            return $this->render('blog/show.html.twig', array('blogpost' => $this->blogPost,
                'latestblogs' => BlogController::getLatestBlogs($this),
                'tophotblogs' => BlogController::getTopHotBlogs($this),
                'is_original' => true,
                'lastblog' => $this->findLastBlog($blogId),
                'nextblog' => $this->findNextBlog($blogId),
                'pagination' => $pagination,
                'tags' => $this->getAllTagNames()
            ));
        } else {
            return $this->render('blog/nofound.html.twig', array(
                'latestblogs' => BlogController::getLatestBlogs($this),
                'tophotblogs' => BlogController::getTopHotBlogs($this)
            ));
        }
    }

    private function findLastBlog($blogId)
    {
        $blog = null;
        $blogId--;
        while ($blogId >= 0) {
            $blog = $this->blogPostRepository->find($blogId);
            if (!empty($blog)) {
                break;
            } else {
                $blogId--;
            }
        }
        return $blog;
    }

    private function findNextBlog($blogId)
    {
        $blog = null;
        $blogId++;
        $tryCount = 20;
        while ($tryCount >= 0) {
            $blog = $this->blogPostRepository->find($blogId);
            if (!empty($blog)) {
                break;
            } else {
                $blogId++;
                $tryCount--;
            }
        }
        return $blog;
    }

    public function searchAction(Request $request)
    {
        $query = $request->get('q');
        $finder = $this->container->get('fos_elastica.finder.app.blogpost');
        $paginator = $this->get('knp_paginator');
        $results = $finder->createPaginatorAdapter($query);
        $pagination = $paginator->paginate($results, 1, 10);

        return $this->render('blog/search.html.twig', array('pagination' => $pagination,
            'query' => $query,
            'latestblogs' => BlogController::getLatestBlogs($this),
            'tophotblogs' => BlogController::getTopHotBlogs($this)));
    }

    public static function getLatestBlogs($contoller)
    {
        $blogPostRepository = $contoller->getDoctrine()->getRepository('AppBundle:BlogPost');
        $blogposts = $blogPostRepository->findBy(array(), array('createTime' => 'DESC'), 5);
        return $blogposts;
    }

    public static function getTopHotBlogs($contoller)
    {
        $blogPostRepository = $contoller->getDoctrine()->getRepository('AppBundle:BlogPost');
        $tophotblogs = $blogPostRepository->findBy(array(), array('pv' => 'DESC'), 5);
        return $tophotblogs;
    }

    public static function genRandList($min, $max, $num)
    {
        $num = min($num, $max-$min);
        $map = array();
        while (sizeof($map) < $num) {
            $r = rand($min, $max-1);
            $map[$r] = 1;
        }
        return $map;
    }
}
