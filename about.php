<?php
$page_title = 'About - Blood Donation System';
require_once __DIR__ . '/includes/auth.php';
include __DIR__ . '/includes/header.php';
?>

<div class="page-header">
    <h2><i class="fa-solid fa-circle-info"></i> About Us</h2>
    <p style="color:#666;">Learn more about the Online Blood Donation Record Tracking System and our mission.</p>
</div>

<div style="display:grid; grid-template-columns: 2fr 1fr; gap: 20px;">
    <div style="background:#fff; border:1px solid #e5e7eb; border-radius:14px; padding:22px; box-shadow: 0 10px 30px rgba(17,24,39,0.06);">
        <h3 style="margin:0 0 12px 0; color:#111827;">
            <i class="fa-solid fa-heart-pulse" style="color:#d32f2f;"></i> Our Mission
        </h3>
        <p style="color:#4b5563; line-height:1.8; margin:0 0 14px 0;">
            The <strong>Online Blood Donation Record Tracking System</strong> is built to help donors and Red Cross staff
            track blood donations digitally, encourage consistent donation habits, and recognize donors who reach
            key milestones.
        </p>

        <h3 style="margin:18px 0 12px 0; color:#111827;">
            <i class="fa-solid fa-bullseye" style="color:#d32f2f;"></i> What this system helps you do
        </h3>

        <div style="display:grid; grid-template-columns: 1fr 1fr; gap: 12px;">
            <div style="background:#f9fafb; border:1px solid #e5e7eb; border-radius:14px; padding:14px;">
                <div style="font-weight:800; color:#111827; margin-bottom:6px;">
                    <i class="fa-solid fa-shield-halved" style="color:#d32f2f;"></i> Secure accounts
                </div>
                <div style="color:#6b7280; line-height:1.6;">Role-based login for donors and admins with password hashing.</div>
            </div>

            <div style="background:#f9fafb; border:1px solid #e5e7eb; border-radius:14px; padding:14px;">
                <div style="font-weight:800; color:#111827; margin-bottom:6px;">
                    <i class="fa-solid fa-list-check" style="color:#d32f2f;"></i> Track donations
                </div>
                <div style="color:#6b7280; line-height:1.6;">Donation history, approvals workflow, and analytics.</div>
            </div>

            <div style="background:#f9fafb; border:1px solid #e5e7eb; border-radius:14px; padding:14px;">
                <div style="font-weight:800; color:#111827; margin-bottom:6px;">
                    <i class="fa-solid fa-chart-line" style="color:#d32f2f;"></i> Progress & milestones
                </div>
                <div style="color:#6b7280; line-height:1.6;">Automatic progress calculation and milestone tracking.</div>
            </div>

            <div style="background:#f9fafb; border:1px solid #e5e7eb; border-radius:14px; padding:14px;">
                <div style="font-weight:800; color:#111827; margin-bottom:6px;">
                    <i class="fa-solid fa-award" style="color:#d32f2f;"></i> Certificates
                </div>
                <div style="color:#6b7280; line-height:1.6;">Recognition certificates are generated after milestones (e.g., 10 donations).</div>
            </div>
        </div>

        <div style="margin-top:18px; background:#fff3e0; border:1px solid #fde68a; border-left:6px solid #f59e0b; border-radius:14px; padding:14px;">
            <div style="font-weight:800; color:#92400e;">
                <i class="fa-solid fa-triangle-exclamation"></i> Important Note
            </div>
            <div style="color:#92400e; margin-top:6px; line-height:1.6;">
                This system stores donation records and approvals. Actual donation eligibility rules depend on official
                medical guidelines and the issuing organization’s policies.
            </div>
        </div>
    </div>

    <div style="background:#fff; border:1px solid #e5e7eb; border-radius:14px; padding:22px; box-shadow: 0 10px 30px rgba(17,24,39,0.06);">
        <h3 style="margin:0 0 12px 0; color:#111827;">
            <i class="fa-solid fa-phone-volume" style="color:#d32f2f;"></i> Need help?
        </h3>
        <p style="color:#6b7280; line-height:1.7;">
            If you have questions, feedback, or want to report an issue, you can reach us via the contact form.
        </p>

        <a href="contact.php"
           style="display:inline-flex; align-items:center; gap:10px; margin-top:14px; background:#d32f2f; color:#fff; padding:12px 14px; border-radius:12px; font-weight:800; text-decoration:none;">
            <i class="fa-solid fa-paper-plane"></i> Contact Support
        </a>

        <div style="margin-top:18px; color:#6b7280; font-size:0.92rem; line-height:1.7;">
            <div style="font-weight:800; color:#111827; margin-bottom:6px;">
                <i class="fa-solid fa-location-dot" style="color:#d32f2f;"></i> Office
            </div>
            Red Cross Headquarters (Example)<br />
            City, Country
        </div>
    </div>
</div>

<style>
@media (max-width: 900px){
  div[style*="grid-template-columns: 2fr 1fr"]{ grid-template-columns: 1fr !important; }
  div[style*="grid-template-columns: 1fr 1fr"]{ grid-template-columns: 1fr !important; }
}
</style>

<?php include __DIR__ . '/includes/footer.php'; ?>