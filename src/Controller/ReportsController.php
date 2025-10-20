<?php

namespace App\Controller;

use App\Service\UsageReportService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/reports')]
#[IsGranted('ROLE_USER')]
class ReportsController extends AbstractController
{
    public function __construct(
        private UsageReportService $usageReportService
    ) {}

    #[Route('/', name: 'reports_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $user = $this->getUser();
        
        // Default date range: last 30 days
        $endDate = new \DateTime();
        $startDate = (clone $endDate)->modify('-30 days');
        
        // Get custom date range from request
        if ($request->query->has('start_date') && $request->query->has('end_date')) {
            try {
                $startDate = new \DateTime($request->query->get('start_date'));
                $endDate = new \DateTime($request->query->get('end_date'));
            } catch (\Exception $e) {
                // Fall back to default range
            }
        }
        
        // Get user statistics
        $userStats = $this->usageReportService->getUserStats($user, $startDate, $endDate);
        $formattedUserStats = $this->usageReportService->getFormattedStats($userStats);
        
        // Get user trends for charts
        $trends = $this->usageReportService->getUserTrends($user, 30);
        
        return $this->render('reports/index.html.twig', [
            'user_stats' => $formattedUserStats,
            'trends' => $trends,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'date_range' => $this->getDateRangeOptions()
        ]);
    }

    #[Route('/global', name: 'reports_global', methods: ['GET'])]
    #[IsGranted('ROLE_ADMIN')]
    public function global(Request $request): Response
    {
        // Default date range: last 30 days
        $endDate = new \DateTime();
        $startDate = (clone $endDate)->modify('-30 days');
        
        // Get custom date range from request
        if ($request->query->has('start_date') && $request->query->has('end_date')) {
            try {
                $startDate = new \DateTime($request->query->get('start_date'));
                $endDate = new \DateTime($request->query->get('end_date'));
            } catch (\Exception $e) {
                // Fall back to default range
            }
        }
        
        // Get global statistics
        $globalStats = $this->usageReportService->getGlobalStats($startDate, $endDate);
        $formattedGlobalStats = $this->usageReportService->getFormattedStats($globalStats);
        
        return $this->render('reports/global.html.twig', [
            'global_stats' => $formattedGlobalStats,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'date_range' => $this->getDateRangeOptions()
        ]);
    }

    #[Route('/api/user-stats', name: 'api_user_stats', methods: ['GET'])]
    public function getUserStatsApi(Request $request): JsonResponse
    {
        $user = $this->getUser();
        
        try {
            $startDate = new \DateTime($request->query->get('start_date'));
            $endDate = new \DateTime($request->query->get('end_date'));
        } catch (\Exception $e) {
            return new JsonResponse(['error' => 'Invalid date format'], 400);
        }
        
        $stats = $this->usageReportService->getUserStats($user, $startDate, $endDate);
        
        return new JsonResponse([
            'success' => true,
            'data' => $stats
        ]);
    }

    #[Route('/api/global-stats', name: 'api_global_stats', methods: ['GET'])]
    #[IsGranted('ROLE_ADMIN')]
    public function getGlobalStatsApi(Request $request): JsonResponse
    {
        try {
            $startDate = new \DateTime($request->query->get('start_date'));
            $endDate = new \DateTime($request->query->get('end_date'));
        } catch (\Exception $e) {
            return new JsonResponse(['error' => 'Invalid date format'], 400);
        }
        
        $stats = $this->usageReportService->getGlobalStats($startDate, $endDate);
        
        return new JsonResponse([
            'success' => true,
            'data' => $stats
        ]);
    }

    #[Route('/api/trends', name: 'api_trends', methods: ['GET'])]
    public function getTrendsApi(Request $request): JsonResponse
    {
        $user = $this->getUser();
        $days = (int) $request->query->get('days', 30);
        
        $trends = $this->usageReportService->getUserTrends($user, $days);
        
        return new JsonResponse([
            'success' => true,
            'data' => $trends
        ]);
    }

    #[Route('/api/charts/hourly-activity', name: 'api_hourly_activity', methods: ['GET'])]
    public function getHourlyActivityChart(Request $request): JsonResponse
    {
        $user = $this->getUser();
        
        try {
            $startDate = new \DateTime($request->query->get('start_date'));
            $endDate = new \DateTime($request->query->get('end_date'));
        } catch (\Exception $e) {
            return new JsonResponse(['error' => 'Invalid date format'], 400);
        }
        
        $stats = $this->usageReportService->getUserStats($user, $startDate, $endDate);
        
        $chartData = [
            'labels' => array_map(function($hour) {
                return sprintf('%02d:00', $hour);
            }, range(0, 23)),
            'datasets' => [
                [
                    'label' => 'Activité par heure',
                    'data' => array_values($stats['hourly_activity']),
                    'backgroundColor' => 'rgba(37, 99, 235, 0.2)',
                    'borderColor' => 'rgba(37, 99, 235, 1)',
                    'borderWidth' => 1
                ]
            ]
        ];
        
        return new JsonResponse([
            'success' => true,
            'data' => $chartData
        ]);
    }

    #[Route('/api/charts/file-types', name: 'api_file_types', methods: ['GET'])]
    public function getFileTypesChart(Request $request): JsonResponse
    {
        $user = $this->getUser();
        
        try {
            $startDate = new \DateTime($request->query->get('start_date'));
            $endDate = new \DateTime($request->query->get('end_date'));
        } catch (\Exception $e) {
            return new JsonResponse(['error' => 'Invalid date format'], 400);
        }
        
        $stats = $this->usageReportService->getUserStats($user, $startDate, $endDate);
        
        // Get top 10 file types
        $topFileTypes = array_slice($stats['file_types'], 0, 10, true);
        
        $chartData = [
            'labels' => array_keys($topFileTypes),
            'datasets' => [
                [
                    'label' => 'Nombre de fichiers',
                    'data' => array_values($topFileTypes),
                    'backgroundColor' => [
                        'rgba(239, 68, 68, 0.8)',   // Red
                        'rgba(59, 130, 246, 0.8)',  // Blue
                        'rgba(16, 185, 129, 0.8)',  // Green
                        'rgba(245, 158, 11, 0.8)',  // Yellow
                        'rgba(139, 92, 246, 0.8)',  // Purple
                        'rgba(236, 72, 153, 0.8)',  // Pink
                        'rgba(14, 165, 233, 0.8)',  // Light Blue
                        'rgba(34, 197, 94, 0.8)',   // Light Green
                        'rgba(251, 146, 60, 0.8)',  // Orange
                        'rgba(168, 85, 247, 0.8)',  // Violet
                    ]
                ]
            ]
        ];
        
        return new JsonResponse([
            'success' => true,
            'data' => $chartData
        ]);
    }

    #[Route('/api/charts/daily-trends', name: 'api_daily_trends', methods: ['GET'])]
    public function getDailyTrendsChart(Request $request): JsonResponse
    {
        $user = $this->getUser();
        $days = (int) $request->query->get('days', 30);
        
        $trends = $this->usageReportService->getUserTrends($user, $days);
        
        $chartData = [
            'labels' => array_column($trends, 'date'),
            'datasets' => [
                [
                    'label' => 'Uploads',
                    'data' => array_column($trends, 'uploads'),
                    'backgroundColor' => 'rgba(16, 185, 129, 0.2)',
                    'borderColor' => 'rgba(16, 185, 129, 1)',
                    'borderWidth' => 2,
                    'tension' => 0.4
                ],
                [
                    'label' => 'Téléchargements',
                    'data' => array_column($trends, 'downloads'),
                    'backgroundColor' => 'rgba(59, 130, 246, 0.2)',
                    'borderColor' => 'rgba(59, 130, 246, 1)',
                    'borderWidth' => 2,
                    'tension' => 0.4
                ]
            ]
        ];
        
        return new JsonResponse([
            'success' => true,
            'data' => $chartData
        ]);
    }

    private function getDateRangeOptions(): array
    {
        return [
            'today' => [
                'label' => 'Aujourd\'hui',
                'start' => (new \DateTime())->format('Y-m-d'),
                'end' => (new \DateTime())->format('Y-m-d')
            ],
            'yesterday' => [
                'label' => 'Hier',
                'start' => (new \DateTime('-1 day'))->format('Y-m-d'),
                'end' => (new \DateTime('-1 day'))->format('Y-m-d')
            ],
            'last_7_days' => [
                'label' => '7 derniers jours',
                'start' => (new \DateTime('-7 days'))->format('Y-m-d'),
                'end' => (new \DateTime())->format('Y-m-d')
            ],
            'last_30_days' => [
                'label' => '30 derniers jours',
                'start' => (new \DateTime('-30 days'))->format('Y-m-d'),
                'end' => (new \DateTime())->format('Y-m-d')
            ],
            'last_90_days' => [
                'label' => '90 derniers jours',
                'start' => (new \DateTime('-90 days'))->format('Y-m-d'),
                'end' => (new \DateTime())->format('Y-m-d')
            ],
            'this_month' => [
                'label' => 'Ce mois',
                'start' => (new \DateTime('first day of this month'))->format('Y-m-d'),
                'end' => (new \DateTime())->format('Y-m-d')
            ],
            'last_month' => [
                'label' => 'Mois dernier',
                'start' => (new \DateTime('first day of last month'))->format('Y-m-d'),
                'end' => (new \DateTime('last day of last month'))->format('Y-m-d')
            ]
        ];
    }
}
