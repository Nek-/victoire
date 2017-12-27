<?php

namespace Victoire\Bundle\SeoBundle\Controller;

use Pagerfanta\Adapter\DoctrineORMAdapter;
use Pagerfanta\Pagerfanta;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Victoire\Bundle\CoreBundle\Controller\VictoireAlertifyControllerTrait;
use Victoire\Bundle\CoreBundle\Entity\Link;
use Victoire\Bundle\SeoBundle\Entity\Error404;
use Victoire\Bundle\SeoBundle\Entity\ErrorRedirection;
use Victoire\Bundle\SeoBundle\Entity\HttpError;
use Victoire\Bundle\SeoBundle\Form\RedirectionType;
use Victoire\Bundle\SeoBundle\Repository\HttpErrorRepository;

/**
 * Class Error404Controller.
 *
 * @Route("/error404")
 */
class Error404Controller extends Controller
{
    use VictoireAlertifyControllerTrait;

    /**
     * @Route("/index", name="victoire_404_index")
     *
     * @Method("GET")
     *
     * @param Request $request
     *
     * @return Response
     */
    public function indexAction(Request $request)
    {
        /** @var HttpErrorRepository $errorRepository */
        $errorRepository = $this->getDoctrine()->getManager()->getRepository('VictoireSeoBundle:HttpError');

        // Fetch errors + build pager

        $errorRoute = $errorRepository->getRouteErrors();
        $pagerRoute = new Pagerfanta(new DoctrineORMAdapter($errorRoute));
        $pagerRoute->setMaxPerPage(100);
        $pagerRoute->setCurrentPage($request->query->get('page', 1));

        $errorFile = $errorRepository->getFileErrors();
        $pagerFile = new Pagerfanta(new DoctrineORMAdapter($errorFile));
        $pagerFile->setMaxPerPage(100);
        $pagerFile->setCurrentPage($request->query->get('page', 1));

        // Build forms

        $forms = [];
        $errors = array_merge($errorRoute->getQuery()->getResult(), $errorFile->getQuery()->getResult());

        /** @var Error404 $error */
        foreach ($errors as $error) {
            $redirection = new ErrorRedirection();
            $redirection->setError($error);
            $forms[$error->getId()] = $this->getError404RedirectionForm($redirection)->createView();
        }

        // Return datas

        return $this->render($this->getBaseTemplatePath().':index.html.twig', [
            'errorsRoute' => $pagerRoute,
            'errorsFile'  => $pagerFile,
            'forms'       => $forms,
        ]);
    }

    /**
     * @Route("/{id}/redirect", name="victoire_404_redirect")
     *
     * @Method("POST")
     *
     * @param Request  $request
     * @param Error404 $error404
     *
     * @return JsonResponse|Response
     */
    public function redirectAction(Request $request, Error404 $error404)
    {
        $redirection = new ErrorRedirection();
        $redirection->setError($error404);

        $form = $this->getError404RedirectionForm($redirection);

        $form->handleRequest($request);
        if ($request->query->get('novalidate', false) === false) {
            if ($form->isValid()) {
                if ($redirection->getLink()->getLinkType() !== Link::TYPE_NONE) {
                    $em = $this->getDoctrine()->getManager();
                    $error404->setRedirection($redirection);

                    $em->persist($redirection);
                    $em->flush();

                    $this->congrat($this->get('translator')->trans('alert.error_404.redirect.success'));

                    return $this->returnAfterRemoval($error404);
                } else {
                    // force form error when linkType === none
                    $form->addError(new FormError('This value should not be blank.'));
                }
            } else {
                $this->warn($this->get('translator')->trans('alert.error_404.form.error'));
            }

            return new Response($this->renderView('@VictoireSeo/Error404/_item.html.twig', [
                'form'     => $form->createView(),
                'error'    => $error404,
                'isOpened' => true,
            ]));
        }

        // rebuild form to avoid wrong form error
        $form = $this->getError404RedirectionForm($redirection);

        return new JsonResponse([
            'html' => $this->renderView('@VictoireSeo/Error404/_item.html.twig', [
                'form'     => $form->createView(),
                'error'    => $error404,
                'isOpened' => true,
            ]),
        ]);
    }

    /**
     * @Route("/{id}/delete", name="victoire_404_delete")
     *
     * @Method("DELETE")
     *
     * @param Error404 $error404
     *
     * @return Response
     */
    public function deleteAction(Error404 $error404)
    {
        $em = $this->getDoctrine()->getManager();

        $em->remove($error404);
        $em->flush();

        $this->congrat($this->get('translator')->trans('alert.error_404.delete.success'));

        return $this->returnAfterRemoval($error404);
    }

    /**
     * Remove error if there is more than one record, else return _empty template.
     *
     * @param Error404 $error404
     *
     * @return Response
     */
    private function returnAfterRemoval(Error404 $error404)
    {
        $em = $this->getDoctrine()->getManager();

        /** @var HttpErrorRepository $errorRepository */
        $errorRepository = $em->getRepository('VictoireSeoBundle:HttpError');

        $errors = ($error404->getType() == HttpError::TYPE_ROUTE)
            ? $errorRepository->getRouteErrors()
            : $errorRepository->getFileErrors();

        if (0 == count($errors->getQuery()->getResult())) {
            return new Response($this->renderView('@VictoireSeo/Error404/_empty.html.twig'), 200, [
                'X-Inject-Alertify' => true,
            ]);
        }

        return new Response(null, 200, [
            'X-VIC-Remove'      => '100ms',
            'X-Inject-Alertify' => true,
        ]);
    }

    /**
     * @param ErrorRedirection $redirection
     *
     * @return \Symfony\Component\Form\Form
     */
    private function getError404RedirectionForm(ErrorRedirection $redirection)
    {
        $containerId = sprintf('#404-%d-item-container', $redirection->getError()->getId());

        $action = $this->generateUrl('victoire_404_redirect', ['id' => $redirection->getError()->getId()]);

        return $this->createForm(RedirectionType::class, $redirection, [
            'method'      => 'POST',
            'action'      => $action,
            'containerId' => $containerId,
            'attr'        => [
                'v-ic-post-to' => $action,
                'v-ic-target'  => $containerId,
            ],
        ]);
    }

    /**
     * @return string
     */
    protected function getBaseTemplatePath()
    {
        return 'VictoireSeoBundle:Error404';
    }
}
