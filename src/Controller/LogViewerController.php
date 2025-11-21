<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\SecurityBundle\Attribute\IsGranted;

#[Route('/crud/logs')]
#[IsGranted('ROLE_ADMIN')]
class LogViewerController extends AbstractController
{
    #[Route('', methods: ['GET'])]
    public function viewLogs(Request $request): JsonResponse
    {
        $projectDir = $this->getParameter('kernel.project_dir');
        $logFile = $projectDir . '/var/log/dev.log';
        $lines = (int)$request->query->get('lines', 100);
        $filter = $request->query->get('filter', ''); // 'error', 'warning', 'info', etc.
        
        if (!file_exists($logFile)) {
            return new JsonResponse([
                'error' => 'Log file not found',
                'path' => $logFile
            ], 404);
        }

        // Read the log file
        $content = file_get_contents($logFile);
        $allLines = explode("\n", $content);
        
        // Get last N lines
        $recentLines = array_slice($allLines, -$lines);
        
        // Filter if requested
        if ($filter) {
            $recentLines = array_filter($recentLines, function($line) use ($filter) {
                return stripos($line, $filter) !== false || 
                       stripos($line, strtoupper($filter)) !== false;
            });
            $recentLines = array_values($recentLines);
        }
        
        // Parse log entries
        $entries = [];
        foreach ($recentLines as $line) {
            if (empty(trim($line))) continue;
            
            // Try to parse Symfony log format: [timestamp] level.message context
            if (preg_match('/^\[([^\]]+)\]\s+(\w+)\.(\w+):\s*(.+?)(?:\s+\{.*\})?$/', $line, $matches)) {
                $entries[] = [
                    'timestamp' => $matches[1],
                    'level' => $matches[2],
                    'channel' => $matches[3],
                    'message' => $matches[4],
                    'raw' => $line
                ];
            } else {
                $entries[] = [
                    'raw' => $line
                ];
            }
        }
        
        return new JsonResponse([
            'total_lines' => count($recentLines),
            'filter' => $filter,
            'entries' => array_reverse($entries), // Most recent first
            'log_file' => $logFile,
            'file_size' => filesize($logFile),
            'last_modified' => date('Y-m-d H:i:s', filemtime($logFile))
        ], 200);
    }
    
    #[Route('/errors', methods: ['GET'])]
    public function viewErrors(Request $request): JsonResponse
    {
        $projectDir = $this->getParameter('kernel.project_dir');
        $logFile = $projectDir . '/var/log/dev.log';
        $lines = (int)$request->query->get('lines', 200);
        
        if (!file_exists($logFile)) {
            return new JsonResponse(['error' => 'Log file not found'], 404);
        }

        $content = file_get_contents($logFile);
        $allLines = explode("\n", $content);
        $recentLines = array_slice($allLines, -$lines);
        
        // Filter only errors and critical messages
        $errorLines = array_filter($recentLines, function($line) {
            return stripos($line, 'ERROR') !== false || 
                   stripos($line, 'CRITICAL') !== false ||
                   stripos($line, '⚠️') !== false ||
                   stripos($line, 'Exception') !== false;
        });
        
        return new JsonResponse([
            'total_errors' => count($errorLines),
            'errors' => array_reverse(array_values($errorLines))
        ], 200);
    }
    
    #[Route('/clear', methods: ['POST'])]
    public function clearLogs(): JsonResponse
    {
        $projectDir = $this->getParameter('kernel.project_dir');
        $logFile = $projectDir . '/var/log/dev.log';
        
        if (file_exists($logFile)) {
            file_put_contents($logFile, '');
        }
        
        return new JsonResponse(['message' => 'Logs cleared'], 200);
    }
}

