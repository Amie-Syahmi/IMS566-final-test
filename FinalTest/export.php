<?php
require_once 'config.php';
require_once 'tcpdf/tcpdf.php';

// Create new PDF document
$pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

// Set document information
$pdf->SetCreator('Application Review System');
$pdf->SetAuthor('Admin');
$pdf->SetTitle('Application Reviews Export');
$pdf->SetSubject('Application Reviews');

// Add a page
$pdf->AddPage();

// Set font
$pdf->SetFont('helvetica', 'B', 16);
$pdf->Cell(0, 10, 'Application Reviews Export', 0, 1, 'C');
$pdf->SetFont('helvetica', '', 12);

// Get all applications with their average ratings
$query = "SELECT a.*, c.title AS category_title, 
          (SELECT AVG(rating) FROM comments WHERE application_id = a.id) AS avg_rating
          FROM applications a
          LEFT JOIN categories c ON a.category_id = c.id
          ORDER BY a.posted_date DESC";
$result = mysqli_query($conn, $query);
$applications = mysqli_fetch_all($result, MYSQLI_ASSOC);

// Add content
foreach ($applications as $app) {
    $pdf->SetFont('helvetica', 'B', 14);
    $pdf->Cell(0, 10, $app['title'], 0, 1);
    
    $pdf->SetFont('helvetica', '', 12);
    $pdf->Cell(0, 10, 'Category: ' . ($app['category_title'] ?? 'Uncategorized'), 0, 1);
    $pdf->Cell(0, 10, 'Author: ' . $app['author'], 0, 1);
    $pdf->Cell(0, 10, 'Posted: ' . date('M d, Y H:i', strtotime($app['posted_date'])), 0, 1);
    $pdf->Cell(0, 10, 'Status: ' . $app['status'], 0, 1);
    $pdf->Cell(0, 10, 'Rating: ' . round($app['avg_rating'], 1) . ' ★', 0, 1);
    
    $pdf->MultiCell(0, 10, $app['review'], 0, 'L');
    $pdf->Ln(5);
    
    // Add a line separator
    $pdf->Line(10, $pdf->GetY(), 200, $pdf->GetY());
    $pdf->Ln(10);
}

// Close and output PDF document
$pdf->Output('application_reviews_export.pdf', 'D');

// Close database connection
mysqli_close($conn);
?>