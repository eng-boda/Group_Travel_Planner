<?php
/**
 * EmailController.php
 * Module 6 — Sending Emails or Notifications
 *
 * Uses PHP built-in mail(). No external library needed.
 * Make sure your server has a configured sendmail/SMTP.
 */
class EmailController
{
    private const FROM_NAME  = 'TripSync';
    private const FROM_EMAIL = 'no-reply@tripsync.app'; // change to your domain

    // ── Core send helper ───────────────────────────────────────────────────────
    private function send(string $to, string $subject, string $html): bool
    {
        $headers  = "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
        $headers .= "From: " . self::FROM_NAME . " <" . self::FROM_EMAIL . ">\r\n";
        $sent = @mail($to, $subject, $html, $headers);
        if (!$sent) error_log("[EmailController] Failed → to=$to subject=$subject");
        return $sent;
    }

    // ── Shared HTML shell ──────────────────────────────────────────────────────
    private function wrap(string $title, string $inner): string
    {
        return "<!DOCTYPE html><html lang='en'><head><meta charset='UTF-8'>
<style>
  body{font-family:Arial,sans-serif;background:#f4f6fb;margin:0;padding:0}
  .wrap{max-width:580px;margin:36px auto;background:#fff;border-radius:12px;
        overflow:hidden;box-shadow:0 4px 20px rgba(0,0,0,.08)}
  .hdr{background:linear-gradient(135deg,#6366f1,#8b5cf6);padding:24px 32px;color:#fff}
  .hdr h1{margin:0;font-size:1.3rem}.hdr p{margin:4px 0 0;opacity:.85;font-size:.85rem}
  .body{padding:24px 32px;color:#1f2937;line-height:1.6}
  .body p{margin:0 0 .9rem}
  .btn{display:inline-block;background:#6366f1;color:#fff!important;
       text-decoration:none;padding:10px 22px;border-radius:8px;font-weight:600;font-size:.9rem}
  .box{background:#fef2f2;border:1px solid #fca5a5;border-radius:8px;padding:14px 18px;margin:1rem 0}
  .box.green{background:#f0fdf4;border-color:#86efac}
  .box.yellow{background:#fffbeb;border-color:#fde68a}
  .tag{display:inline-block;background:#ede9fe;color:#6366f1;padding:2px 10px;
       border-radius:20px;font-size:.78rem;font-weight:600;margin-bottom:.8rem}
  .foot{padding:14px 32px;background:#f8f9fa;font-size:.75rem;color:#9ca3af;border-top:1px solid #e5e7eb}
  table.data{width:100%;border-collapse:collapse;font-size:.85rem;margin-top:.5rem}
  table.data th{text-align:left;padding:6px 8px;border-bottom:2px solid #e5e7eb;color:#6b7280}
  table.data td{padding:6px 8px;border-bottom:1px solid #f3f4f6}
</style></head><body>
<div class='wrap'>
  <div class='hdr'><h1>✈ TripSync</h1><p>Collaborative Trip Planner</p></div>
  <div class='body'>$inner</div>
  <div class='foot'>You received this because you are a member of a TripSync trip. Safe to ignore if unexpected.</div>
</div></body></html>";
    }

    // ══════════════════════════════════════════════════════════════════════════
    //  PUBLIC METHODS
    // ══════════════════════════════════════════════════════════════════════════

    /**
     * Budget Threshold Alert — sent to ALL members when spend crosses threshold.
     * Called by expenses.php (Function 19 + Module 6 combined).
     *
     * @param array  $members    [ ['name'=>..,'email'=>..], ... ]
     * @param string $tripName
     * @param float  $spent
     * @param float  $budget
     * @param float  $threshold  percentage (e.g. 80)
     * @param float  $pct        current percentage used
     */
    public function sendBudgetAlert(array $members, string $tripName, float $spent,
                                    float $budget, float $threshold, float $pct): void
    {
        $subject  = "⚠️ Budget alert for \"$tripName\"";
        $remaining = max($budget - $spent, 0);

        foreach ($members as $m) {
            if (empty($m['email'])) continue;
            $name = htmlspecialchars($m['name'] ?? 'there', ENT_QUOTES);
            $inner = "
              <span class='tag'>Budget Alert</span>
              <p>Hi $name,</p>
              <p>The trip <strong>" . htmlspecialchars($tripName, ENT_QUOTES) . "</strong>
                 has exceeded its alert threshold of <strong>" . round($threshold) . "%</strong>.</p>
              <div class='box'>
                <strong>⚠️ " . round($pct) . "% of budget used</strong><br>
                Spent: <strong>\$" . number_format($spent, 2) . "</strong>
                out of <strong>\$" . number_format($budget, 2) . "</strong><br>
                Remaining: <strong>\$" . number_format($remaining, 2) . "</strong>
              </div>
              <p>Please review your group expenses on TripSync to stay on track.</p>";
            $this->send($m['email'], $subject, $this->wrap($subject, $inner));
        }
    }

    /**
     * Settlement Sign-Off Request — sent to a member asking them to approve settlement.
     * Called when leader initiates settlement (Function 20 + Module 6).
     */
    public function sendSettlementRequest(string $toEmail, string $toName,
                                          string $tripName, float $balance,
                                          string $approveUrl): bool
    {
        $subject = "✅ Please sign off on the settlement for \"$tripName\"";
        $balanceStr = $balance >= 0
            ? "you are owed <strong>\$" . number_format(abs($balance), 2) . "</strong>"
            : "you owe <strong>\$" . number_format(abs($balance), 2) . "</strong>";

        $inner = "
          <span class='tag'>Settlement</span>
          <p>Hi " . htmlspecialchars($toName, ENT_QUOTES) . ",</p>
          <p>The trip <strong>" . htmlspecialchars($tripName, ENT_QUOTES) . "</strong>
             is ready to be settled. According to the final calculation, $balanceStr.</p>
          <div class='box green'>
            Your balance: <strong>" . ($balance >= 0 ? '+' : '') . "\$" . number_format($balance, 2) . "</strong>
          </div>
          <p>Please log in and approve the settlement so the trip can be marked as complete:</p>
          <a href='" . htmlspecialchars($approveUrl, ENT_QUOTES) . "' class='btn'>Review &amp; Sign Off →</a>";
        return $this->send($toEmail, $subject, $this->wrap($subject, $inner));
    }

    /**
     * Settlement Completed — sent to all members once everyone signed off.
     */
    public function sendSettlementCompleted(array $members, string $tripName): void
    {
        $subject = "🎉 Settlement completed for \"$tripName\"";
        foreach ($members as $m) {
            if (empty($m['email'])) continue;
            $name = htmlspecialchars($m['name'] ?? 'there', ENT_QUOTES);
            $inner = "
              <span class='tag'>Settlement Complete</span>
              <p>Hi $name,</p>
              <p>Great news! All members have signed off on the settlement for
                 <strong>" . htmlspecialchars($tripName, ENT_QUOTES) . "</strong>.
                 The trip is now marked as <strong>Settled</strong>. 🎉</p>
              <div class='box green'>All balances have been agreed upon by all members.</div>";
            $this->send($m['email'], $subject, $this->wrap($subject, $inner));
        }
    }

    /**
     * Kitty contribution confirmation — sent to member after they add to the kitty.
     */
    public function sendKittyContribution(string $toEmail, string $toName,
                                           string $tripName, float $amount,
                                           float $newBalance): bool
    {
        $subject = "💰 Kitty contribution confirmed for \"$tripName\"";
        $inner = "
          <span class='tag'>Kitty</span>
          <p>Hi " . htmlspecialchars($toName, ENT_QUOTES) . ",</p>
          <p>Your contribution of <strong>\$" . number_format($amount, 2) . "</strong>
             to the group kitty for
             <strong>" . htmlspecialchars($tripName, ENT_QUOTES) . "</strong>
             has been recorded.</p>
          <div class='box green'>
            New kitty balance: <strong>\$" . number_format($newBalance, 2) . "</strong>
          </div>";
        return $this->send($toEmail, $subject, $this->wrap($subject, $inner));
    }
}
?>
