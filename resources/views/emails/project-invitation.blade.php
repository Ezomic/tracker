<!DOCTYPE html>
<html>
<body style="font-family: sans-serif; color: #17181A;">
    <p>
        @if ($inviterName)
            {{ $inviterName }} invited you to join
        @else
            You've been invited to join
        @endif
        <strong>{{ $projectName }}</strong> on Tracker as {{ $roleLabel }}.
    </p>
    <p>
        <a href="{{ $acceptUrl }}" style="display: inline-block; background: #17181A; color: #ffffff; padding: 10px 18px; border-radius: 6px; text-decoration: none;">
            Accept invitation
        </a>
    </p>
    <p style="color: #6b7280; font-size: 14px;">
        This invitation expires in 7 days. If you weren't expecting it, you can ignore this email.
    </p>
</body>
</html>
