<?php

namespace App\Controller;

use App\Entity\ServiceClick;
use App\Entity\ProductOrder;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/statistics')]
class StatisticsController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em
    ) {}

    #[Route('/service-click', methods: ['POST'])]
    public function trackServiceClick(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $serviceName = $data['service_name'] ?? null;

        if (!$serviceName) {
            return $this->json(['error' => 'service_name is required'], 400);
        }

        $click = new ServiceClick();
        $click->setServiceName($serviceName);
        $click->setUserAgent($request->headers->get('User-Agent'));
        $click->setIpAddress($request->getClientIp());

        $this->em->persist($click);
        $this->em->flush();

        return $this->json(['success' => true, 'id' => $click->getId()]);
    }

    #[Route('/product-order', methods: ['POST'])]
    public function trackProductOrder(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        $order = new ProductOrder();
        $order->setProductId($data['product_id'] ?? null);
        $order->setProductReference($data['product_reference'] ?? null);
        $order->setProductTitle($data['product_title'] ?? '');
        $order->setCustomerEmail($data['customer_email'] ?? null);
        $order->setCustomerPhone($data['customer_phone'] ?? '');
        $order->setOrderType($data['order_type'] ?? 'mail'); // 'mail' or 'whatsapp'
        $order->setUserAgent($request->headers->get('User-Agent'));
        $order->setIpAddress($request->getClientIp());

        $this->em->persist($order);
        $this->em->flush();

        return $this->json(['success' => true, 'id' => $order->getId()]);
    }

    #[Route('/stats', methods: ['GET'])]
    public function getStats(Request $request): JsonResponse
    {
        $startDate = $request->query->get('start_date');
        $endDate = $request->query->get('end_date');

        // Service clicks stats
        $serviceClicksQb = $this->em->getRepository(ServiceClick::class)->createQueryBuilder('sc');
        if ($startDate) {
            $serviceClicksQb->andWhere('sc.created_at >= :start')
                ->setParameter('start', new \DateTime($startDate));
        }
        if ($endDate) {
            $serviceClicksQb->andWhere('sc.created_at <= :end')
                ->setParameter('end', new \DateTime($endDate));
        }
        $totalServiceClicks = $serviceClicksQb->select('COUNT(sc.id)')->getQuery()->getSingleScalarResult();

        $serviceClicksByService = $this->em->getRepository(ServiceClick::class)
            ->createQueryBuilder('sc')
            ->select('sc.service_name, COUNT(sc.id) as count')
            ->groupBy('sc.service_name')
            ->getQuery()
            ->getResult();

        // Product orders stats
        $productOrdersQb = $this->em->getRepository(ProductOrder::class)->createQueryBuilder('po');
        if ($startDate) {
            $productOrdersQb->andWhere('po.created_at >= :start')
                ->setParameter('start', new \DateTime($startDate));
        }
        if ($endDate) {
            $productOrdersQb->andWhere('po.created_at <= :end')
                ->setParameter('end', new \DateTime($endDate));
        }
        $totalProductOrders = $productOrdersQb->select('COUNT(po.id)')->getQuery()->getSingleScalarResult();

        $ordersByType = $this->em->getRepository(ProductOrder::class)
            ->createQueryBuilder('po')
            ->select('po.order_type, COUNT(po.id) as count')
            ->groupBy('po.order_type')
            ->getQuery()
            ->getResult();

        $ordersByProduct = $this->em->getRepository(ProductOrder::class)
            ->createQueryBuilder('po')
            ->select('po.product_id, po.product_title, COUNT(po.id) as count')
            ->groupBy('po.product_id, po.product_title')
            ->orderBy('count', 'DESC')
            ->setMaxResults(20)
            ->getQuery()
            ->getResult();

        return $this->json([
            'service_clicks' => [
                'total' => (int)$totalServiceClicks,
                'by_service' => $serviceClicksByService,
            ],
            'product_orders' => [
                'total' => (int)$totalProductOrders,
                'by_type' => $ordersByType,
                'by_product' => $ordersByProduct,
            ],
        ]);
    }

    #[Route('/orders', methods: ['GET'])]
    public function getOrders(Request $request): JsonResponse
    {
        $startDate = $request->query->get('start_date');
        $endDate = $request->query->get('end_date');
        $page = max(1, (int)$request->query->get('page', 1));
        $limit = max(1, min(100, (int)$request->query->get('limit', 50)));
        $offset = ($page - 1) * $limit;

        $qb = $this->em->getRepository(ProductOrder::class)
            ->createQueryBuilder('po')
            ->orderBy('po.created_at', 'DESC');

        if ($startDate) {
            $qb->andWhere('po.created_at >= :start')
                ->setParameter('start', new \DateTime($startDate));
        }
        if ($endDate) {
            $qb->andWhere('po.created_at <= :end')
                ->setParameter('end', new \DateTime($endDate));
        }

        $total = $qb->select('COUNT(po.id)')->getQuery()->getSingleScalarResult();

        $orders = $qb->select('po')
            ->setFirstResult($offset)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        $data = array_map(function (ProductOrder $order) {
            return [
                'id' => $order->getId(),
                'product_id' => $order->getProductId(),
                'product_reference' => $order->getProductReference(),
                'product_title' => $order->getProductTitle(),
                'customer_email' => $order->getCustomerEmail(),
                'customer_phone' => $order->getCustomerPhone(),
                'order_type' => $order->getOrderType(),
                'created_at' => $order->getCreatedAt()->format('Y-m-d H:i:s'),
                'ip_address' => $order->getIpAddress(),
            ];
        }, $orders);

        return $this->json([
            'rows' => $data,
            'total' => (int)$total,
            'page' => $page,
            'limit' => $limit,
        ]);
    }
}

