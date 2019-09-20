<?php

namespace Yosimitso\WorkingForumBundle\Event;

use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Event\LifecycleEventArgs;
use Symfony\Component\Translation\TranslatorInterface;
use Yosimitso\WorkingForumBundle\Entity\Subscription;
use Yosimitso\WorkingForumBundle\Entity\Post;
use Yosimitso\WorkingForumBundle\Service\SubscriptionService;

/**
 * Class PostEvent
 * @package Yosimitso\WorkingForumBundle\Event
 */
class PostEvent
{
    /**
     * @var int
     */
    private $floodLimit;
    /**
     * @var TranslatorInterface
     */
    private $translator;
    /**
     * @var EntityManager
     */
    private $em;
    /**
     * @var SubscriptionService
     */
    private $subscriptionService;

    /**
     * @var array
     */
    private $paramSubscription;

    /**
     * PostEvent constructor.
     * @param int $floodLimit
     * @param TranslatorInterface $translator
     * @param SubscriptionService $subscriptionService
     */
    public function __construct(int $floodLimit, TranslatorInterface $translator, SubscriptionService $subscriptionService, $paramSubscription)
    {
        $this->floodLimit = $floodLimit;
        $this->translator = $translator;
        $this->subscriptionService = $subscriptionService;
        $this->paramSubscription = $paramSubscription;
    }

    /**
     * @param LifecycleEventArgs $args
     * @throws \Exception
     */
    public function prePersist(LifecycleEventArgs $args)
    {
        $entity = $args->getEntity();
        $this->em = $args->getEntityManager();

        if (!$entity instanceof Post) {
            return;
        }

        if (!$this->isFlood($entity)) {
            return;
        }

        return;
    }

    /**
     * @param LifecycleEventArgs $args
     * @throws \Exception
     */
    public function postPersist(LifecycleEventArgs $args)
    {
        $entity = $args->getEntity();

        if (!$entity instanceof Post) {
            return;
        }

        if ($this->paramSubscription['enable']) {
            $this->subscriptionService->notifySubscriptions($entity);

            if ($entity->getAddSubscription()) {
                $this->addSubscription($entity);
            }
        }

    }

    /**
     * Check if this new post is considered as flood
     * @param $entity
     * @return bool
     * @throws \Exception
     */
    private function isFlood($entity)
    {
        $dateNow = new \DateTime('now');
        $floodLimit = new \DateTime('-'.$this->floodLimit.' seconds');

        if (!is_null($entity->getUser()->getLastReplyDate()) && $floodLimit <= $entity->getUser()->getLastReplyDate()) { // USER IS FLOODING
            throw new \Exception($this->translator->trans('forum.error_flood', ['%second%' => $this->floodLimit], 'YosimitsoWorkingForumBundle'));
        }

        $entity->getUser()->setLastReplyDate($dateNow);
        return true;
    }

    /**
     * User wants to subscribe to the thread
     * @param $entity
     * @throws \Doctrine\ORM\OptimisticLockException
     */
    public function addSubscription($entity)
    {
        $checkSubscription = $this->em->getRepository(Subscription::class)->findBy(['thread' => $entity->getThread(), 'user' => $entity->getUser()]);
        if (empty($checkSubscription) || is_null($checkSubscription)) { // NOT ALREADY SUBSCRIBED
            $subscription = new Subscription($entity->getThread(), $entity->getUser());
            $this->em->persist($subscription);
            $this->em->flush();
        }

    }
}