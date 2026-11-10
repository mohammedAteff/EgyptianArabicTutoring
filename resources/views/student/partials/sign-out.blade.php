<form method="POST" action="{{ route('student.logout') }}" class="student-sign-out">
    @csrf
    <button type="submit" class="student-nav-link"><x-student-portal-icon name="logout" /><span>{{ __('Sign Out') }}</span></button>
</form>
