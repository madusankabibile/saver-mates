@php
    $isMine = $message->sender_id === $me->id;
    $group = $group ?? false;
    $removed = $message->isRemoved();
    $manageable = $isMine && ! $removed;
@endphp

{{-- Group events ("Nimal added Sanduni") are a centred line, not a bubble. --}}
@if ($message->isEvent())
    <div class="chat-event" data-message="{{ $message->id }}">
        <span>
            <b>{{ $isMine ? 'You' : $message->sender->name }}</b> {{ $message->body }}
            <time>{{ $message->created_at->format('g:i a') }}</time>
        </span>
    </div>
@else
<div class="bubble-row {{ $isMine ? 'is-mine' : '' }} {{ $removed ? 'is-removed' : '' }}"
     data-message="{{ $message->id }}" data-sender="{{ $message->sender_id }}">
    @unless ($isMine)
        <x-avatar :user="$message->sender" :size="28" />
    @endunless

    <div class="bubble">
        {{-- Message menu (top right): Edit, Info and Delete on your own
             messages, Copy text on any message with text. --}}
        @if (! $removed && ($manageable || filled($message->body)))
            <button class="msg-menu-btn" type="button" data-msg-menu aria-label="Message options" aria-haspopup="menu"
                    @if ($manageable)
                        data-edit-url="{{ route('chat.messages.update', [$message->conversation_id, $message->id]) }}"
                        data-delete-url="{{ route('chat.messages.destroy', [$message->conversation_id, $message->id]) }}"
                        data-info-url="{{ route('chat.messages.info', [$message->conversation_id, $message->id]) }}"
                    @endif>
                <x-icon name="more" :size="16" />
            </button>
        @endif

        @if ($group && ! $isMine)
            <span class="bubble-sender" style="--hue: {{ ($message->sender_id * 47) % 360 }}">{{ $message->sender->name }}</span>
        @endif

        @if ($removed)
            <span class="bubble-removed">
                <x-icon name="close" :size="14" />
                {{ $isMine ? 'You deleted this message' : 'This message was deleted' }}
            </span>
        @else
            @foreach ($message->attachments as $attachment)
                @if ($attachment->isImage())
                    <a class="bubble-img" href="{{ $attachment->url() }}"
                       data-lightbox="{{ $attachment->url() }}" data-mime="{{ $attachment->mime }}">
                        <img src="{{ $attachment->url() }}" alt="{{ $attachment->name }}" loading="lazy">
                    </a>
                @else
                    <a class="bubble-file" href="{{ $attachment->downloadUrl() }}">
                        <span class="file-glyph" style="background:{{ $attachment->source === 'drive' ? 'var(--jade)' : '#f8b24a' }}">
                            {{ $attachment->source === 'drive' ? 'DRV' : 'PC' }}
                        </span>
                        <span class="bf-body">
                            <span class="bf-name">{{ $attachment->name }}</span>
                            <span class="bf-meta">
                                {{ $attachment->readableSize() }} &middot;
                                {{ $attachment->source === 'drive' ? 'from drive' : 'uploaded' }}
                            </span>
                        </span>
                        <x-icon name="download" :size="16" />
                    </a>
                @endif
            @endforeach

            {{-- Only this span is pre-wrap, so template indentation around it is not
                 rendered as real whitespace inside the bubble. --}}
            @if (filled($message->body))<span class="bubble-text" data-body>{{ $message->body }}</span>@endif
        @endif

        <span class="bubble-time">
            @if ($message->edited_at && ! $removed)
                <span class="bubble-edited" title="Edited {{ $message->edited_at->format('D j M, g:i a') }}">edited</span>
            @endif
            {{ $message->created_at->format('g:i a') }}

            {{-- Ticks on your own messages: one = sent, two = read (by everyone,
                 in a group). The message menu's Info shows the details. --}}
            @if ($manageable)
                @if ($group)
                    @php
                        $counted = array_filter($readers ?? [], fn ($r) => $r[0] < $message->id);
                        $total = count($counted);
                        $seen = count(array_filter($counted, fn ($r) => $r[1] >= $message->id));
                        $state = $total > 0 && $seen >= $total ? 'read' : ($seen > 0 ? 'partial' : 'sent');
                    @endphp
                    <span class="receipt is-{{ $state }}" data-receipt title="{{ $seen ? 'Read by '.$seen.' of '.$total : 'Sent' }}">
                        @include('chat.partials.ticks')
                        <span class="receipt-count" data-receipt-count>{{ $seen && $state !== 'read' ? $seen : '' }}</span>
                    </span>
                @else
                    <span class="receipt is-{{ $message->read_at ? 'read' : 'sent' }}" data-receipt
                          title="{{ $message->read_at ? 'Read '.$message->read_at->format('g:i a') : 'Sent' }}">
                        @include('chat.partials.ticks')
                    </span>
                @endif
            @endif
        </span>
    </div>
</div>
@endif
