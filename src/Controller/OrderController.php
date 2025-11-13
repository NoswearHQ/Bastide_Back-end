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

#[Route('/api/orders')]
class OrderController extends AbstractController
{
    private const SMTP_HOST = 'bastidemedical.tn';
    private const SMTP_PORT = 465;
    private const SMTP_USERNAME = 'commandebastidesite@bastidemedical.tn';
    private const SMTP_PASSWORD = 'MDZqe2U@CJvy7Qr';
    private const RECIPIENT_EMAIL = 'contact@bastidemedical.tn';
    private const SENDER_EMAIL = 'commandebastidesite@bastidemedical.tn';

    public function __construct(private LoggerInterface $logger) {}

    #[Route('/send', methods: ['POST'])]
    public function sendOrder(Request $request, MailerInterface $mailer): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);
            
            if (!$data) {
                return new JsonResponse([
                    'success' => false,
                    'error' => 'Invalid JSON'
                ], 400);
            }

            // Validate required fields
            $validator = Validation::createValidator();
            $violations = $validator->validate($data, new Assert\Collection([
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
                'subject' => new Assert\Optional(),
            ]));

            if (count($violations) > 0) {
                $errors = [];
                foreach ($violations as $violation) {
                    $errors[] = $violation->getMessage();
                }
                return new JsonResponse([
                    'success' => false,
                    'error' => implode(', ', $errors)
                ], 400);
            }

            $userEmail = $data['email'];
            $userPhone = $data['phone'];
            $productName = $data['product_name'];
            $productReference = $data['product_reference'] ?? 'N/A';
            $subject = $data['subject'] ?? "Commande de produit : {$productName}";

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

            // Create email with both text and HTML versions
            $email = (new Email())
                ->from(self::SENDER_EMAIL)
                ->to(self::RECIPIENT_EMAIL)
                ->replyTo($userEmail)
                ->subject($subject)
                ->text($emailBodyText)
                ->html($emailBodyHtml);

            // Create custom SMTP transport with provided credentials
            // For SSL on port 465, use smtps:// scheme
            $dsn = sprintf(
                'smtps://%s:%s@%s:%d',
                urlencode(self::SMTP_USERNAME),
                urlencode(self::SMTP_PASSWORD),
                self::SMTP_HOST,
                self::SMTP_PORT
            );
            
            // Create transport from DSN
            $transport = Transport::fromDsn($dsn);
            
            // Create a custom mailer with the SMTP transport
            $customMailer = new \Symfony\Component\Mailer\Mailer($transport);

            // Send email
            $customMailer->send($email);

            $this->logger->info('Order email sent successfully', [
                'user_email' => $userEmail,
                'product_name' => $productName,
            ]);

            return new JsonResponse([
                'success' => true,
                'message' => 'Order email sent successfully',
            ], 200);

        } catch (\Exception $e) {
            // Log the error with full details
            $this->logger->error('Failed to send order email', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Return clean error response without exposing internal details in production
            $errorMessage = 'Failed to send email. Please try again later.';
            if (($_ENV['APP_ENV'] ?? 'prod') === 'dev') {
                $errorMessage = 'Failed to send email: ' . $e->getMessage();
            }

            return new JsonResponse([
                'success' => false,
                'error' => $errorMessage,
            ], 500);
        }
    }
}

