<?php

namespace AppBundle\Controller;

require_once 'html2text.php' ;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use AppBundle\Entity\Article;
use AppBundle\Entity\Comment;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use EWZ\Bundle\RecaptchaBundle\Form\Type\EWZRecaptchaType;
use EWZ\Bundle\RecaptchaBundle\Validator\Constraints\IsTrue as RecaptchaTrue;
use Symfony\Component\OptionsResolver\OptionsResolver;

class DefaultController extends Controller
{
    /**
     * @Route("/", name="homepage")
     */
    public function indexAction(Request $request)
    {
        $em = $this->getDoctrine()->getManager();
        $id = $em->createQueryBuilder()
        /* ->select('MAX'(e.id)) */
        ->select('MIN(e.id)')
        ->from('AppBundle:Article', 'e')
        ->getQuery()
        ->getSingleScalarResult();

        $repository = $this->getDoctrine()->getRepository('AppBundle:Article');
        $com_repository = $this->getDoctrine()->getRepository('AppBundle:Comment');
        $articles = $repository->findBy(array('id' => $id));
        $c_comments = count($com_repository->findBy(array('art_id' => $id, 'validated' => 1)));

    

        return $this->render('default/index2.html.twig', array('active_page' => 'home', 'articles' => $articles, 'comments' => $c_comments));
    }


    /**
     * @Route("/contact/", name="contact")
     */

    public function contactAction(request $request){

        $defaultData = array('message' => 'Type your message here');
        $form = $this->createFormBuilder($defaultData)
        ->add('name', TextType::class, array('constraints' => new Length(array('min'=>3)),))
        ->add('email', EmailType::class)
        ->add('title', TextType::class)
        ->add('message', TextAreaType::class)
        ->add('send', SubmitType::class)
        ->add('recaptcha', EWZRecaptchaType::class, array(
        'attr' => array(
            'options' => array(
                'theme' => 'light',
                'type'  => 'image',
                'size'  => 'normal',
                'defer' => true,
                'async' => true
            )
        ),
        'mapped'      => false,
        'constraints' => array(
            new RecaptchaTrue()
        )
        ))
        ->getForm();

        $form->handleRequest($request);

        if ($form->isValid() && $form->isSubmitted()){
            $data = $form->getData();
            $name = $form["name"]->getData();
            $email = $form["email"]->getData();
            $message = \Swift_Message::newInstance()
            ->SetSubject($form["title"]->getData())
            ->setFrom($form["email"]->getData())
            ->setTo('contact@kmeulders.be')
            ->setBody($form["message"]->getData());
            $this->get('mailer')->send($message);
            
            return $this->render('Emails/sent.html.twig', array('name'=> $name, 'active_page' => 'contact'));
        }


        return $this->render('default/contact.html.twig', array('active_page' => 'contact', 'form' => $form->createView()));
    }

    /**
     * @Route("/about/", name="about")
     */

    public function aboutAction(){
        return $this->render('default/about.html.twig', array('active_page' => 'about'));
    }

    /**
     * @Route("/weblog/", name="weblog")
     */

    public function weblogAction(){
         $repository = $this->getDoctrine()->getRepository('AppBundle:Article');
        $articles = $repository->findBy([], ['id' => 'DESC']);

        return $this->render('default/weblog.html.twig', array('active_page' => 'weblog', 'articles' => $articles));
    }

    /**
     * @Route("/weblog/{id}-{title}/", name="weblog-article")
     */

    public function weblogIdAction($id, $title){
        $repository = $this->getDoctrine()->getRepository('AppBundle:Article');
        $com_repository = $this->getDoctrine()->getRepository('AppBundle:Comment');
        $articles = $repository->findBy(array('id' => $id));
        $c_comments = count($com_repository->findBy(array('art_id' => $id, 'validated' => 1)));


        return $this->render('default/article.html.twig', array('active_page' => 'weblog', 'articles' => $articles, 'comments' => $c_comments));
    }

    /**
     * @Route("/weblog/{id}-{title}/comments/", name="weblog-artcomments")
     */

    public function weblogComments(Request $request, $id, $title){
        $repository = $this->getDoctrine()->getRepository('AppBundle:Article');
        $com_repository = $this->getDoctrine()->getRepository('AppBundle:Comment');
        $articles = $repository->findBy(array('id' => $id));
        $comments = $com_repository->findBy(array('art_id' => $id));


        $comment = new Comment();

        $form = $this->createFormBuilder($comment)
        ->add('author', TextType::class)
        ->add('email', EmailType::class)
        ->add('comment', TextareaType::class)
        ->add('add', SubmitType::class, array('label' => 'Post Comment'))
        ->add('art_id', HiddenType::class, array(
        'data' => $id))
        ->add('recaptcha', EWZRecaptchaType::class, array(
        'attr' => array(
            'options' => array(
                'theme' => 'light',
                'type'  => 'image',
                'size'  => 'normal',
                'defer' => true,
                'async' => true
            )
        ),
        'mapped'      => false,
        'constraints' => array(
            new RecaptchaTrue()
        )
        ))
        ->getForm();

        $form->handleRequest($request);

         if ($form->isSubmitted() && $form->isValid()) {
            $comment = $form->getData();
            $name = $form["author"]->getData();
            $em = $this->getDoctrine()->getManager();
            $em->persist($comment);
            $em->flush();

            return $this->render('Comments/commentsent.html.twig', array('name'=> $name, 'active_page' => 'contact'));
    }

        return $this->render('default/articlecomments.html.twig', array('active_page' => 'weblog', 'articles' => $articles, 'comments' => $comments, 'form' => $form->createView()));
}

    /**
    *   @Route("/admini/", name="admini")
    */

    public function adminiAction(){
        error_reporting(0);
        session_destroy();
        return $this->render('default/admini.html.twig', array('active_page' => 'none'));
    }


    /**
    *   @Route("/admini/login.php", name="login")
    */

    public function loginAction(){
        error_reporting(0);
        if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

        if (!$_SESSION['login_user']){
       $login_user = $_POST['login_user'];
       $_SESSION['login_user'] = $login_user;
       }

       if (!$_SESSION['login_pwd']){
       $login_pwd = $_POST['login_pwd'];
       $_SESSION['login_pwd'] = $login_pwd;
        }

       if($_SESSION['login_user'] != ##### || 
        $_SESSION['login_pwd'] != ##### ) {
            return $this->render('error404.html.twig',
            array('status_text' => "Wrong login details",
                    'title_text' => "Login Error"  
                ));
       }

       else {
        $repository = $this->getDoctrine()->getRepository('AppBundle:Article');
        $articles = $repository->findBy([], ['id' => 'DESC']);
        $com_repository = $this->getDoctrine()->getRepository('AppBundle:Comment');
        $comments = $com_repository->findByValidated(0, ['id' => 'DESC']);
        $vcomments = $com_repository->findByValidated(1, ['id' => 'DESC']);
       return $this->render('default/login.html.twig', array('login_user' => $_SESSION['login_user'], 'login_pwd' => $_SESSION['login_pwd'],
            'articles' => $articles, 'comments' => $comments,
            'vcomments' => $vcomments, 'active_page' => 'none'
            ));
            }
 
    }

    /**
    *   @Route("/admini/articleadd", name="articleadd")
    */

    public function articleAdd(Request $request){

        $article = new Article();

        $form = $this->createFormBuilder($article)
        ->add('title', TextType::class)
        ->add('text', TextareaType::class)
        ->add('add', SubmitType::class, array('label' => 'Create Article'))
        ->getForm();

        $form->handleRequest($request);

         if ($form->isSubmitted() && $form->isValid()) {
            $article = $form->getData();
            $em = $this->getDoctrine()->getManager();
            $em->persist($article);
            $em->flush();

    return $this->redirectToRoute('login');
    }

        return $this->render('default/articleadd.html.twig', array('login_user' => $_SESSION['login_user'], 'active_page' => 'admin',
            'form' => $form->createView(),
            ));
    }

    /**
    *   @Route("/admini/delete/{id}", name="articledel")
    */

    public function articleDel($id){

        
    $em = $this->getDoctrine()->getManager();
    $article = $em->getRepository('AppBundle:Article')->findOneBy(array('id' => $id));
    $comments = $em->getRepository('AppBundle:Comment')->findBy(array('art_id' => $id));

    
    $em->remove($article);
    $em->flush();

    $connection = $em->getConnection();
    $statement = $connection->prepare("ALTER TABLE Article AUTO_INCREMENT = 1");
    $statement->execute();

    return $this->redirectToRoute('login');

    }

    /**
    *   @Route("/admini/edit/{id}", name="articledit")
    */

    public function articleEdit(Request $request, $id){
        $em = $this->getDoctrine()->getManager();
        $article = $em->getRepository('AppBundle:Article')->findOneBy(array('id' => $id));

        $form = $this->createFormBuilder($article)
        ->add('title', TextType::class)
        ->add('text', TextareaType::class)
        ->add('add', SubmitType::class, array('label' => 'Edit Article'))
        ->getForm();

        $temptitle = $article->getTitle();
        $temptitle = ucwords(str_replace("-", " ", $temptitle));
        $form->get('title')->setData($temptitle);

        $form->handleRequest($request);

         if ($form->isSubmitted() && $form->isValid()) {
            $article = $form->getData();
            $em = $this->getDoctrine()->getManager();
            $em->persist($article);
            $em->flush();

    return $this->redirectToRoute('login');
    }

        return $this->render('default/articledit.html.twig', array('login_user' => $_SESSION['login_user'], 'active_page' => 'admin',
            'form' => $form->createView(), 'article' => $article,
            ));

    }

    /**
    *   @Route("/admini/cdelete/{id}", name="commentdel")
    */

    public function commentDel($id){

        
    $em = $this->getDoctrine()->getManager();
    $comment = $em->getRepository('AppBundle:Comment')->findOneBy(array('id' => $id));

    $em->remove($comment);
    $em->flush();

    $connection = $em->getConnection();
    $statement = $connection->prepare("ALTER TABLE Article AUTO_INCREMENT = 1");
    $statement->execute();

    return $this->redirectToRoute('login');

    }

      /**
    *   @Route("/admini/cvalidate/{id}", name="commentval")
    */

    public function commentVal($id){

        
    $em = $this->getDoctrine()->getManager();
    $comment = $em->getRepository('AppBundle:Comment')->findOneBy(array('id' => $id));

    $comment->setValidated(1);
    $em->flush();

    $connection = $em->getConnection();
    $statement = $connection->prepare("ALTER TABLE Article AUTO_INCREMENT = 1");
    $statement->execute();

    return $this->redirectToRoute('login');

    }

}
