<?php
require_once __DIR__ . '/mailer.php';

function notifyEnrollmentReceived($parent_email, $parent_name, $student_name, $ref_number) {
  $subject = "Enrollment Application Received — $ref_number";
  $body = "
    <p>Dear <strong>$parent_name</strong>,</p>
    <p>We have received the enrollment application for <strong>$student_name</strong>.</p>
    <p><strong>Reference Number:</strong> <span style='font-size:18px;font-weight:800;color:#494C8A;'>$ref_number</span></p>
    <p>Our registrar will review your application within 2–3 school days. You will receive another email once your application has been processed.</p>
    <p>In the meantime, please prepare the following documents:</p>
    <ul>
      <li>PSA Birth Certificate (original or certified true copy)</li>
      <li>Form 138 / Report Card from previous school</li>
      <li>Good Moral Certificate</li>
      <li>2x2 ID Photo (2 pieces, white background)</li>
    </ul>
    <p>You can upload these documents through the <a href='http://localhost/school-registrar/portal/login.php' style='color:#494C8A;font-weight:600;'>Parent Portal</a>.</p>
    <p>Thank you for choosing COJ Catholic Progressive School.</p>
  ";
  return sendEnrollmentEmail($parent_email, $parent_name, $subject, $body);
}

function notifyEnrollmentApproved($parent_email, $parent_name, $student_name, $grade) {
  $subject = "Enrollment Approved — $student_name";
  $body = "
    <p>Dear <strong>$parent_name</strong>,</p>
    <p>We are pleased to inform you that the enrollment of <strong>$student_name</strong> for <strong>$grade</strong> has been <span style='color:#16a34a;font-weight:700;'>APPROVED</span>.</p>
    <p>Please log in to the Parent Portal to:</p>
    <ul>
      <li>View your Statement of Account</li>
      <li>Upload any remaining required documents</li>
      <li>Submit proof of payment</li>
    </ul>
    <p><a href='http://localhost/school-registrar/portal/login.php' style='display:inline-block;padding:10px 24px;background:#494C8A;color:#fff;border-radius:8px;text-decoration:none;font-weight:600;'>Go to Parent Portal</a></p>
  ";
  return sendEnrollmentEmail($parent_email, $parent_name, $subject, $body);
}

function notifyDocumentVerified($parent_email, $parent_name, $student_name, $doc_name) {
  $subject = "Document Verified — $doc_name";
  $body = "
    <p>Dear <strong>$parent_name</strong>,</p>
    <p>The following document for <strong>$student_name</strong> has been <span style='color:#16a34a;font-weight:700;'>VERIFIED</span> by our registrar:</p>
    <p style='background:#f0fdf4;border-left:4px solid #16a34a;padding:12px 16px;border-radius:6px;font-weight:600;'>$doc_name</p>
    <p>Log in to the Parent Portal to check the status of your remaining documents.</p>
  ";
  return sendEnrollmentEmail($parent_email, $parent_name, $subject, $body);
}

function notifyDocumentRejected($parent_email, $parent_name, $student_name, $doc_name) {
  $subject = "Document Needs Resubmission — $doc_name";
  $body = "
    <p>Dear <strong>$parent_name</strong>,</p>
    <p>The following document for <strong>$student_name</strong> requires resubmission:</p>
    <p style='background:#fef2f2;border-left:4px solid #dc2626;padding:12px 16px;border-radius:6px;font-weight:600;'>$doc_name</p>
    <p>Please upload a clearer or correct copy through the Parent Portal.</p>
    <p>If you have questions, please contact the registrar's office directly.</p>
  ";
  return sendEnrollmentEmail($parent_email, $parent_name, $subject, $body);
}

function notifyPaymentReceived($parent_email, $parent_name, $student_name, $amount, $or_number) {
  $subject = "Payment Received — OR# $or_number";
  $body = "
    <p>Dear <strong>$parent_name</strong>,</p>
    <p>We have recorded a payment for <strong>$student_name</strong>:</p>
    <table style='width:100%;border-collapse:collapse;font-size:13px;margin:16px 0;'>
      <tr><td style='padding:8px 12px;background:#f9fafb;border:1px solid #e5e7eb;font-weight:600;'>Amount Paid</td><td style='padding:8px 12px;border:1px solid #e5e7eb;'>₱" . number_format($amount, 2) . "</td></tr>
      <tr><td style='padding:8px 12px;background:#f9fafb;border:1px solid #e5e7eb;font-weight:600;'>OR Number</td><td style='padding:8px 12px;border:1px solid #e5e7eb;'>$or_number</td></tr>
    </table>
    <p>View your full Statement of Account in the Parent Portal.</p>
  ";
  return sendEnrollmentEmail($parent_email, $parent_name, $subject, $body);
}
