<p>Hello,</p>
<p>You have been invited to collaborate on <strong>{{ $invitation->event->name }}</strong> as a <strong>{{ $invitation->role }}</strong>.</p>
<p>
    <a href="{{ url('/register?invitation=' . $invitation->token) }}">
        Create your account to accept this invitation
    </a>
</p>
<p>This invitation expires on {{ $invitation->expires_at->toFormattedDateString() }}.</p>
