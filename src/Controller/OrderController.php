<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mailer\Transport;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Validation;
use Psr\Log\LoggerInterface;
use App\Entity\ProductOrder;
use Doctrine\ORM\EntityManagerInterface;

#[Route('/api/orders')]
class OrderController extends AbstractController
{
    private const SMTP_HOST = 'bastidemedical.tn';
    private const SMTP_PORT = 465;
    private const SMTP_USERNAME = 'commandebastidesite@bastidemedical.tn';
    private const SMTP_PASSWORD = 'MDZqe2U@CJvy7Qr';
    private const RECIPIENT_EMAIL = 'contact@bastidemedical.tn';
    private const SENDER_EMAIL = 'commandebastidesite@bastidemedical.tn';

    private LoggerInterface $orderLogger;
    private EntityManagerInterface $em;

    public function __construct(LoggerInterface $orderEmailLogger, EntityManagerInterface $em)
    {
        $this->orderLogger = $orderEmailLogger;
        $this->em = $em;
    }

    /**
     * Log to order_email channel (writes to var/log/order_email.log)
     */
    private function log(string $level, string $message, array $context = []): void
    {
        $this->orderLogger->log($level, $message, $context);
    }

    #[Route('/send', methods: ['POST'])]
    public function sendOrder(Request $request, MailerInterface $mailer): JsonResponse
    {
        $this->log('info', '=== ORDER EMAIL ENDPOINT CALLED ===');
        
        try {
            // Log raw request body
            $rawBody = $request->getContent();
            $this->log('info', 'Raw request body received', ['body_length' => strlen($rawBody)]);
            
            $data = json_decode($rawBody, true);
            
            if (!$data) {
                $this->log('error', 'Invalid JSON body', ['raw_body' => substr($rawBody, 0, 500)]);
                return new JsonResponse([
                    'success' => false,
                    'error' => 'Invalid JSON'
                ], 400);
            }

            // Log parsed JSON data (hide password if any)
            $logData = $data;
            if (isset($logData['password'])) {
                unset($logData['password']);
            }
            $this->log('info', 'Parsed JSON data', $logData);

            // Validate required fields
            $this->log('info', 'Starting validation');
            $validator = Validation::createValidator();
            
            // Use Collection constraint with allowExtraFields option
            $collectionConstraint = new Assert\Collection(
                fields: [
                    'email' => [
                        new Assert\NotBlank(['message' => 'Email is required']),
                        new Assert\Email(['message' => 'Invalid email format']),
                    ],
                    'phone' => [
                        new Assert\NotBlank(['message' => 'Phone is required']),
                    ],
                    'product_name' => [
                        new Assert\NotBlank(['message' => 'Product name is required']),
                    ],
                    'product_reference' => new Assert\Optional(),
                    'product_id' => new Assert\Optional(), // Allow optional product_id for tracking
                    'subject' => new Assert\Optional(),
                ],
                allowExtraFields: true // This allows any extra fields without validation errors
            );
            
            $violations = $validator->validate($data, $collectionConstraint);

            if (count($violations) > 0) {
                $errors = [];
                $detailedErrors = [];
                foreach ($violations as $violation) {
                    $field = $violation->getPropertyPath();
                    $message = $violation->getMessage();
                    $errors[] = $message;
                    $detailedErrors[] = [
                        'field' => $field,
                        'message' => $message,
                        'invalid_value' => $violation->getInvalidValue(),
                    ];
                }
                $this->log('error', 'Validation failed', [
                    'errors' => $errors,
                    'detailed_errors' => $detailedErrors,
                    'received_data' => $data, // Log the actual data received
                ]);
                return new JsonResponse([
                    'success' => false,
                    'error' => implode(', ', $errors),
                    'details' => $detailedErrors, // Include detailed errors in response
                ], 400);
            }

            $this->log('info', 'Validation passed');
            
            $userEmail = $data['email'];
            $userPhone = $data['phone'];
            $productName = $data['product_name'];
            $productReference = $data['product_reference'] ?? 'N/A';
            $subject = $data['subject'] ?? "Commande de produit : {$productName}";

            $this->log('info', 'Extracted order data', [
                'user_email' => $userEmail,
                'user_phone' => $userPhone,
                'product_name' => $productName,
                'product_reference' => $productReference,
                'subject' => $subject,
            ]);

            // Escape HTML entities for security
            $safeUserEmail = htmlspecialchars($userEmail, ENT_QUOTES, 'UTF-8');
            $safeUserPhone = htmlspecialchars($userPhone, ENT_QUOTES, 'UTF-8');
            $safeProductName = htmlspecialchars($productName, ENT_QUOTES, 'UTF-8');
            $safeProductReference = htmlspecialchars($productReference, ENT_QUOTES, 'UTF-8');
            $safeSubject = htmlspecialchars($subject, ENT_QUOTES, 'UTF-8');

            // Build plain text email body
            $emailBodyText = "Nouvelle commande depuis le site Bastide Tunisie\n\n";
            $emailBodyText .= "Informations du client :\n";
            $emailBodyText .= "- Email : {$userEmail}\n";
            $emailBodyText .= "- Téléphone : {$userPhone}\n\n";
            $emailBodyText .= "Informations du produit :\n";
            $emailBodyText .= "- Nom : {$productName}\n";
            $emailBodyText .= "- Référence : {$productReference}\n\n";
            $emailBodyText .= "Sujet : {$subject}\n";

            // Build HTML email body with proper escaping
            $emailBodyHtml = <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background-color: #009090; color: white; padding: 20px; text-align: center; }
        .content { background-color: #f9f9f9; padding: 20px; margin-top: 20px; }
        .section { margin-bottom: 20px; }
        .section-title { font-weight: bold; color: #009090; margin-bottom: 10px; }
        .info-item { margin: 5px 0; }
        .footer { margin-top: 20px; padding: 20px; text-align: center; color: #666; font-size: 12px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Nouvelle commande depuis le site Bastide Tunisie</h1>
        </div>
        <div class="content">
            <div class="section">
                <div class="section-title">Informations du client :</div>
                <div class="info-item"><strong>Email :</strong> {$safeUserEmail}</div>
                <div class="info-item"><strong>Téléphone :</strong> {$safeUserPhone}</div>
            </div>
            <div class="section">
                <div class="section-title">Informations du produit :</div>
                <div class="info-item"><strong>Nom :</strong> {$safeProductName}</div>
                <div class="info-item"><strong>Référence :</strong> {$safeProductReference}</div>
            </div>
            <div class="section">
                <div class="section-title">Sujet :</div>
                <div class="info-item">{$safeSubject}</div>
            </div>
        </div>
        <div class="footer">
            <p>Cet email a été envoyé automatiquement depuis le site Bastide Tunisie.</p>
        </div>
    </div>
</body>
</html>
HTML;

            $this->log('info', 'Email body created', [
                'text_length' => strlen($emailBodyText),
                'html_length' => strlen($emailBodyHtml),
                'text_preview' => substr($emailBodyText, 0, 200) . '...',
            ]);

            // Create email with both text and HTML versions
            $this->log('info', 'Creating Email object');
            $email = (new Email())
                ->from(self::SENDER_EMAIL)
                ->to(self::RECIPIENT_EMAIL)
                ->replyTo($userEmail)
                ->subject($subject)
                ->text($emailBodyText)
                ->html($emailBodyHtml);

            $this->log('info', 'Email object created', [
                'from' => self::SENDER_EMAIL,
                'to' => self::RECIPIENT_EMAIL,
                'reply_to' => $userEmail,
                'subject' => $subject,
            ]);

            // Create custom SMTP transport with provided credentials
            // For SSL on port 465, use smtps:// scheme with debug enabled
            $this->log('info', 'Building SMTP DSN');
            $dsn = sprintf(
                'smtps://%s:%s@%s:%d',
                urlencode(self::SMTP_USERNAME),
                urlencode(self::SMTP_PASSWORD),
                self::SMTP_HOST,
                self::SMTP_PORT
            );
            
            // Add debug parameter if available (some SMTP transports support this)
            // Note: Symfony Mailer doesn't have a direct debug flag, but we can enable verbose logging
            $this->log('info', 'SMTP DSN created (password hidden)', [
                'dsn_preview' => sprintf(
                    'smtps://%s:***@%s:%d',
                    urlencode(self::SMTP_USERNAME),
                    self::SMTP_HOST,
                    self::SMTP_PORT
                ),
                'host' => self::SMTP_HOST,
                'port' => self::SMTP_PORT,
                'username' => self::SMTP_USERNAME,
            ]);

            // Attempt to create transport
            $this->log('info', 'Attempting to create SMTP transport');
            try {
                $transport = Transport::fromDsn($dsn);
                $this->log('info', 'SMTP transport created successfully');
            } catch (\Throwable $e) {
                $this->log('error', 'Failed to create SMTP transport', [
                    'error_message' => $e->getMessage(),
                    'error_class' => get_class($e),
                    'trace' => $e->getTraceAsString(),
                ]);
                throw $e;
            }
            
            // Create a custom mailer with the SMTP transport
            $this->log('info', 'Creating Mailer instance');
            $customMailer = new \Symfony\Component\Mailer\Mailer($transport);
            $this->log('info', 'Mailer instance created');

            // Attempt to send email
            $this->log('info', '=== ATTEMPTING TO SEND EMAIL ===');
            $this->log('info', 'Calling Mailer::send() method');
            
            try {
                $customMailer->send($email);
                
                // If we reach here, the email was sent successfully
                $this->log('info', '=== EMAIL SENT SUCCESSFULLY ===');
                $this->log('info', 'Mailer::send() completed without exception');
                
                // Track order statistics AFTER successful email send (non-blocking)
                // This is wrapped in try-catch to ensure it never blocks the order flow
                $this->log('info', '=== ATTEMPTING TO TRACK ORDER ===');
                try {
                    $this->trackEmailOrder($data, $request);
                    $this->log('info', '=== ORDER TRACKING COMPLETED ===');
                } catch (\Throwable $trackError) {
                    // Log error but don't fail the request - statistics tracking is non-critical
                    $this->log('error', '=== FAILED TO TRACK EMAIL ORDER STATISTICS ===', [
                        'error' => $trackError->getMessage(),
                        'error_class' => get_class($trackError),
                        'error_file' => $trackError->getFile(),
                        'error_line' => $trackError->getLine(),
                        'trace' => $trackError->getTraceAsString(),
                    ]);
                }
                
                return new JsonResponse([
                    'success' => true,
                    'message' => 'Order email sent successfully',
                ], 200);
                
            } catch (\Throwable $sendException) {
                // Email sending failed
                $this->log('error', '=== EMAIL SENDING FAILED ===');
                $this->log('error', 'Exception during Mailer::send()', [
                    'error_message' => $sendException->getMessage(),
                    'error_class' => get_class($sendException),
                    'error_code' => $sendException->getCode(),
                    'error_file' => $sendException->getFile(),
                    'error_line' => $sendException->getLine(),
                    'full_trace' => $sendException->getTraceAsString(),
                ]);
                
                // Re-throw to be caught by outer catch block
                throw $sendException;
            }

        } catch (\Throwable $e) {
            // Catch ALL exceptions (including \Error, \Exception, etc.)
            $this->log('error', '=== EXCEPTION CAUGHT ===');
            $this->log('error', 'Exception details', [
                'error_message' => $e->getMessage(),
                'error_class' => get_class($e),
                'error_code' => $e->getCode(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'full_trace' => $e->getTraceAsString(),
            ]);

            // Always return error response - NEVER return success on error
            $errorMessage = 'Failed to send email: ' . $e->getMessage();
            
            // In production, we might want to hide some details, but for debugging, show everything
            if (($_ENV['APP_ENV'] ?? 'prod') !== 'dev') {
                // Still log full details, but return a generic message
                $errorMessage = 'Failed to send email. Please try again later.';
            }

            return new JsonResponse([
                'success' => false,
                'error' => $errorMessage,
            ], 500);
        }
    }

    /**
     * Track email order in statistics (non-blocking, safe)
     * This method is called AFTER successful email send and will never throw exceptions
     * that could affect the order submission flow.
     */
    private function trackEmailOrder(array $data, Request $request): void
    {
        $this->log('info', 'trackEmailOrder called', [
            'data_keys' => array_keys($data),
            'has_product_id' => isset($data['product_id']),
        ]);
        
        try {
            // Extract product_id if provided (optional field)
            $productId = isset($data['product_id']) && is_numeric($data['product_id']) 
                ? (int)$data['product_id'] 
                : null;

            $this->log('info', 'Creating ProductOrder entity', [
                'product_id' => $productId,
                'product_name' => $data['product_name'] ?? 'N/A',
                'email' => $data['email'] ?? 'N/A',
                'phone' => $data['phone'] ?? 'N/A',
            ]);

            // Create order tracking entry
            $order = new ProductOrder();
            $order->setProductId($productId);
            $order->setProductReference($data['product_reference'] ?? null);
            $order->setProductTitle($data['product_name'] ?? '');
            $order->setCustomerEmail($data['email'] ?? null);
            $order->setCustomerPhone($data['phone'] ?? '');
            $order->setOrderType('mail');
            $order->setUserAgent($request->headers->get('User-Agent'));
            $order->setIpAddress($request->getClientIp());

            $this->log('info', 'Persisting order to database');
            $this->em->persist($order);
            
            $this->log('info', 'Flushing to database');
            $this->em->flush();

            $orderId = $order->getId();
            $this->log('info', 'Email order tracked successfully', [
                'order_id' => $orderId,
                'product_id' => $productId,
                'product_title' => $order->getProductTitle(),
            ]);
            
            // Verify the order was actually saved
            if (!$orderId) {
                throw new \RuntimeException('Order was persisted but ID is null - flush may have failed silently');
            }
            
        } catch (\Throwable $e) {
            // Re-throw to be caught by caller - this ensures we log but don't break the flow
            $this->log('error', 'Exception in trackEmailOrder', [
                'error' => $e->getMessage(),
                'error_class' => get_class($e),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

}
