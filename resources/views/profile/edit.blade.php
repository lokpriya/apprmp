<x-layouts.standalone title="मेरो प्रोफाइल" subtitle="My Profile">

    <div class="max-w-2xl mx-auto space-y-5">
        <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-6">
            @include('profile.partials.update-profile-information-form')
        </div>

        <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-6">
            @include('profile.partials.update-password-form')
        </div>

        <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-6">
            @include('profile.partials.delete-user-form')
        </div>
    </div>

</x-layouts.standalone>
