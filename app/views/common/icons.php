<?php
/**
 * SVG Icons Helper
 */

function getIcon($name, $size = 24, $class = "") {
    $icons = [
        'settings' => '<svg xmlns="http://www.w3.org/2000/svg" height="'. $size .'px" viewBox="0 -960 960 960" width="'. $size .'px" fill="currentColor" class="'. $class .'"><path d="m370-80-16-128q-13-5-24.5-12T307-235l-119 50L78-375l103-78q-1-7-1-13.5t1-13.5l-103-78 110-190 119 50q11-8 23-15t24-12l16-128h220l16 128q13 5 24.5 12t22.5 15l119-50 110 190-103 78q1 7 1 13.5t-1 13.5l103 78-110 190-119-50q-11 8-23 15t-24 12L590-80H370Zm70-80h80l12-96q26-7 49-21t43-34l89 38 40-68-77-59q4-14 6-28t2-28q0-14-2-28t-6-28l77-59-40-68-89 38q-20-20-43-34t-49-21l-12-96h-80l-12 96q-26 7-49 21t-43 34l-89-38-40 68 77 59q-4 14-6 28t-2 28q0 14 2 28t6 28l-77 59 40 68 89-38q20 20 43 34t49 21l12 96Zm40-240q33 0 56.5-23.5T560-480q0-33-23.5-56.5T480-560q-33 0-56.5 23.5T400-480q0 33 23.5 56.5T480-400Zm0-80Z"/></svg>',
        'history' => '<svg xmlns="http://www.w3.org/2000/svg" height="'. $size .'px" viewBox="0 -960 960 960" width="'. $size .'px" fill="currentColor" class="'. $class .'"><path d="M480-80q-75 0-140.5-28.5t-114-77q-48.5-48.5-77-114T120-440q0-72 27-135.5t75-113.5l65 65q-34 35-51 79.5T219-440q0 108 75.5 183.5T478-181q108 0 183.5-75.5T737-440q0-108-75.5-183.5T478-299q-31 0-56.5 6.5T372-273l68 68-200 40 40-200 64 64q31-29 70.5-44.5T478-359q134 0 228 94t94 228q0 134-94 228t-228 94Zm0-120q-17 0-28.5-11.5T440-240v-160q0-8 3.5-15.5T454-428l114-114q12-12 28-12t28 12q12 12 12 28t-12 28L520-402v162q0 17-11.5 28.5T480-200Z"/></svg>',
        'arrow_back' => '<svg xmlns="http://www.w3.org/2000/svg" height="'. $size .'px" viewBox="0 -960 960 960" width="'. $size .'px" fill="currentColor" class="'. $class .'"><path d="m313-440 224 224-57 56-320-320 320-320 57 56-224 224h487v80H313Z"/></svg>',
        'bus' => '<svg xmlns="http://www.w3.org/2000/svg" height="'. $size .'px" viewBox="0 -960 960 960" width="'. $size .'px" fill="currentColor" class="'. $class .'"><path d="M200-160q-33 0-56.5-23.5T120-240v-400q0-84 55-142t137-58h336q82 0 137 58t55 142v400q0 33-23.5 56.5T760-160v80h-80v-80H280v80h-80v-80Zm80-160h400v-160H280v160Zm0-240h400v-160H280v160Zm-40 400q17 0 28.5-11.5T280-400q0-17-11.5-28.5T240-440q-17 0-28.5 11.5T200-400q0 17 11.5 28.5T240-360Zm480 0q17 0 28.5-11.5T760-400q0-17-11.5-28.5T720-440q-17 0-28.5 11.5T680-400q0 17 11.5 28.5T720-360Z"/></svg>',
        'star' => '<svg xmlns="http://www.w3.org/2000/svg" height="'. $size .'px" viewBox="0 -960 960 960" width="'. $size .'px" fill="currentColor" class="'. $class .'"><path d="m233-120 65-281L80-590l288-25 112-265 112 265 288 25-218 189 65 281-247-149-247 149Z"/></svg>',
        'chevron_right' => '<svg xmlns="http://www.w3.org/2000/svg" height="'. $size .'px" viewBox="0 -960 960 960" width="'. $size .'px" fill="currentColor" class="'. $class .'"><path d="M504-480 320-664l56-56 240 240-240 240-56-56 184-184Z"/></svg>'
    ];

    return $icons[$name] ?? "";
}
