<x-vue-app-layout>
    <x-slot:title>
        Sondage
    </x-slot>

    <x-slot:scripts>
        @vite(['resources/js/poll-vote.js'])
    </x-slot>

    <div
        id="app-poll-vote"
        data-props='@json(["token" => $token])'
    ></div>
</x-vue-app-layout>
