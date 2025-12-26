@props([
    'src' => null,
    'alt' => 'Image',
    'gallery' => false,
    'index' => 0,
])

@php
    $imageId = 'lightbox-' . uniqid();
@endphp

<div 
    x-data="{
        open: false,
        currentIndex: {{ $index }},
        images: @js($gallery ? ($gallery === true ? [] : $gallery) : [$src]),
        zoom: 1,
        panX: 0,
        panY: 0,
        isDragging: false,
        startX: 0,
        startY: 0,
        openLightbox(index = 0) {
            this.currentIndex = index;
            this.open = true;
            document.body.style.overflow = 'hidden';
            this.zoom = 1;
            this.panX = 0;
            this.panY = 0;
        },
        closeLightbox() {
            this.open = false;
            document.body.style.overflow = '';
            this.zoom = 1;
            this.panX = 0;
            this.panY = 0;
        },
        nextImage() {
            if (this.images.length > 1) {
                this.currentIndex = (this.currentIndex + 1) % this.images.length;
                this.zoom = 1;
                this.panX = 0;
                this.panY = 0;
            }
        },
        prevImage() {
            if (this.images.length > 1) {
                this.currentIndex = (this.currentIndex - 1 + this.images.length) % this.images.length;
                this.zoom = 1;
                this.panX = 0;
                this.panY = 0;
            }
        },
        zoomIn() {
            this.zoom = Math.min(this.zoom + 0.25, 3);
        },
        zoomOut() {
            this.zoom = Math.max(this.zoom - 0.25, 1);
            if (this.zoom === 1) {
                this.panX = 0;
                this.panY = 0;
            }
        },
        resetZoom() {
            this.zoom = 1;
            this.panX = 0;
            this.panY = 0;
        },
        handleWheel(e) {
            if (e.deltaY < 0) {
                this.zoomIn();
            } else {
                this.zoomOut();
            }
            e.preventDefault();
        },
        startDrag(e) {
            if (this.zoom > 1) {
                this.isDragging = true;
                this.startX = e.type === 'mousedown' ? e.clientX : e.touches[0].clientX;
                this.startY = e.type === 'mousedown' ? e.clientY : e.touches[0].clientY;
            }
        },
        drag(e) {
            if (this.isDragging && this.zoom > 1) {
                const currentX = e.type === 'mousemove' ? e.clientX : e.touches[0].clientX;
                const currentY = e.type === 'mousemove' ? e.clientY : e.touches[0].clientY;
                this.panX += currentX - this.startX;
                this.panY += currentY - this.startY;
                this.startX = currentX;
                this.startY = currentY;
            }
        },
        endDrag() {
            this.isDragging = false;
        }
    }"
    @keydown.escape.window="closeLightbox()"
    class="inline-block"
>
    <!-- Thumbnail Image (Clickable) -->
    <div 
        @click="openLightbox({{ $gallery ? '$index' : '0' }})"
        class="cursor-pointer transition-transform hover:scale-105 w-full h-full"
        role="button"
        tabindex="0"
        @keydown.enter="openLightbox({{ $gallery ? '$index' : '0' }})"
    >
        {{ $slot }}
    </div>

    <!-- Lightbox Modal -->
    <div
        x-show="open"
        x-cloak
        x-transition:enter="transition ease-out duration-300"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="transition ease-in duration-200"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        class="fixed inset-0 z-[100] flex items-center justify-center bg-black bg-opacity-90 p-2 sm:p-4"
        @click.self="closeLightbox()"
        style="display: none;"
    >
        <!-- Close Button -->
        <button
            @click="closeLightbox()"
            class="absolute top-2 right-2 sm:top-4 sm:right-4 z-10 p-2 rounded-full bg-white/10 hover:bg-white/20 text-white transition-colors backdrop-blur-sm"
            aria-label="Close lightbox"
        >
            <svg class="w-5 h-5 sm:w-6 sm:h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
            </svg>
        </button>

        <!-- Navigation Arrows (if gallery) -->
        <template x-if="images.length > 1">
            <div>
                <button
                    @click="prevImage()"
                    class="absolute left-2 sm:left-4 top-1/2 -translate-y-1/2 z-10 p-2 sm:p-3 rounded-full bg-white/10 hover:bg-white/20 text-white transition-colors backdrop-blur-sm"
                    aria-label="Previous image"
                >
                    <svg class="w-5 h-5 sm:w-6 sm:h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                    </svg>
                </button>
                <button
                    @click="nextImage()"
                    class="absolute right-2 sm:right-4 top-1/2 -translate-y-1/2 z-10 p-2 sm:p-3 rounded-full bg-white/10 hover:bg-white/20 text-white transition-colors backdrop-blur-sm"
                    aria-label="Next image"
                >
                    <svg class="w-5 h-5 sm:w-6 sm:h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                    </svg>
                </button>
            </div>
        </template>

        <!-- Zoom Controls -->
        <div class="absolute bottom-4 left-1/2 -translate-x-1/2 z-10 flex items-center gap-2 p-2 rounded-full bg-white/10 backdrop-blur-sm">
            <button
                @click="zoomOut()"
                class="p-2 rounded-full hover:bg-white/20 text-white transition-colors"
                aria-label="Zoom out"
                :disabled="zoom <= 1"
                :class="zoom <= 1 ? 'opacity-50 cursor-not-allowed' : ''"
            >
                <svg class="w-4 h-4 sm:w-5 sm:h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0zM13 10H7"/>
                </svg>
            </button>
            <span class="text-white text-xs sm:text-sm font-medium min-w-[3rem] text-center" x-text="Math.round(zoom * 100) + '%'"></span>
            <button
                @click="zoomIn()"
                class="p-2 rounded-full hover:bg-white/20 text-white transition-colors"
                aria-label="Zoom in"
                :disabled="zoom >= 3"
                :class="zoom >= 3 ? 'opacity-50 cursor-not-allowed' : ''"
            >
                <svg class="w-4 h-4 sm:w-5 sm:h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0zM10 7v6m3-3H7"/>
                </svg>
            </button>
            <button
                @click="resetZoom()"
                class="p-2 rounded-full hover:bg-white/20 text-white transition-colors ml-2"
                aria-label="Reset zoom"
                x-show="zoom > 1"
            >
                <svg class="w-4 h-4 sm:w-5 sm:h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                </svg>
            </button>
        </div>

        <!-- Image Counter (if gallery) -->
        <template x-if="images.length > 1">
            <div class="absolute top-4 left-1/2 -translate-x-1/2 z-10 px-3 py-1.5 rounded-full bg-white/10 backdrop-blur-sm text-white text-xs sm:text-sm font-medium">
                <span x-text="currentIndex + 1"></span> / <span x-text="images.length"></span>
            </div>
        </template>

        <!-- Image Container -->
        <div
            class="relative w-full h-full flex items-center justify-center"
            @wheel="handleWheel($event)"
            @mousedown="startDrag($event)"
            @mousemove="drag($event)"
            @mouseup="endDrag()"
            @mouseleave="endDrag()"
            @touchstart="startDrag($event)"
            @touchmove="drag($event)"
            @touchend="endDrag()"
        >
            <img
                :src="images[currentIndex]"
                :alt="{{ $alt }}"
                class="max-w-full max-h-full object-contain select-none"
                :style="`transform: scale(${zoom}) translate(${panX / zoom}px, ${panY / zoom}px); transition: ${isDragging ? 'none' : 'transform 0.2s'};`"
                draggable="false"
                @load="zoom = 1; panX = 0; panY = 0;"
            />
        </div>
    </div>
</div>

