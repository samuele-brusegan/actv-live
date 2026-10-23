/** Lateral lanes for overlapping route segments, computed in projected coordinates. */
(function (root) {
    'use strict';
    const dot = (a, b) => a.x * b.x + a.y * b.y;
    const subtract = (a, b) => ({ x: a.x - b.x, y: a.y - b.y });
    const interpolate = (a, b, t) => ({ x: a.x + (b.x - a.x) * t, y: a.y + (b.y - a.y) * t });

    function sharedInterval(a, b, tolerance) {
        // A crossing is not a shared road. Compare only nearly parallel segments.
        if (Math.abs(dot(a.unit, b.unit)) < 0.985) return null;
        const start = dot(subtract(b.start, a.start), a.unit) / a.length;
        const end = dot(subtract(b.end, a.start), a.unit) / a.length;
        const low = Math.max(0, Math.min(start, end));
        const high = Math.min(1, Math.max(start, end));
        const minimumOverlap = Math.min(tolerance, Math.min(a.length, b.length) / 2);
        if ((high - low) * a.length < minimumOverlap) return null;
        for (const t of [low, high]) {
            const delta = subtract(interpolate(a.start, a.end, t), b.start);
            if (Math.abs(delta.x * b.unit.y - delta.y * b.unit.x) > tolerance) return null;
        }
        return [low, high];
    }

    /**
     * Index segments along their length, rather than filling their bounding boxes.
     * This also handles long diagonal segments without a quadratic grid footprint.
     */
    function segmentCells(segment, cellSize) {
        const cells = new Set();
        const steps = Math.max(1, Math.ceil(segment.length / (cellSize / 2)));
        for (let step = 0; step <= steps; step++) {
            const point = interpolate(segment.start, segment.end, step / steps);
            cells.add(`${Math.floor(point.x / cellSize)},${Math.floor(point.y / cellSize)}`);
        }
        return cells;
    }

    function layout(routes, { tolerance = 8, cellSize = 128 } = {}) {
        const segments = [];
        const routeSegments = routes.map(route => {
            const result = [];
            for (let i = 1; i < route.points.length; i++) {
                const start = route.points[i - 1], end = route.points[i];
                const vector = subtract(end, start);
                const length = Math.hypot(vector.x, vector.y);
                if (length < 0.001) continue;
                const segment = { start, end, length, unit: { x: vector.x / length, y: vector.y / length }, key: route.key, overlaps: [] };
                result.push(segment);
                segments.push(segment);
            }
            return result;
        });
        const grid = new Map();
        segments.forEach((segment, index) => {
            const cells = segmentCells(segment, cellSize);
            const candidates = new Set();
            for (const cell of cells) {
                const [x, y] = cell.split(',').map(Number);
                for (let dx = -1; dx <= 1; dx++) for (let dy = -1; dy <= 1; dy++) {
                    for (const other of grid.get(`${x + dx},${y + dy}`) || []) candidates.add(other);
                }
            }
            candidates.forEach(otherIndex => {
                const other = segments[otherIndex];
                if (other.key === segment.key) return;
                const interval = sharedInterval(segment, other, tolerance);
                const reverse = sharedInterval(other, segment, tolerance);
                if (!interval || !reverse) return;
                segment.overlaps.push({ interval, segment: other });
                other.overlaps.push({ interval: reverse, segment });
            });
            for (const cell of cells) {
                if (!grid.has(cell)) grid.set(cell, []);
                grid.get(cell).push(index);
            }
        });
        return routeSegments.map(list => list.flatMap(segment => {
            const cuts = [...new Set([0, 1, ...segment.overlaps.flatMap(overlap => overlap.interval)])].sort((a, b) => a - b);
            const fragments = [];
            for (let i = 1; i < cuts.length; i++) {
                if (cuts[i] - cuts[i - 1] < 1e-8) continue;
                const middle = (cuts[i] + cuts[i - 1]) / 2;
                const neighbours = new Map([[segment.key, segment]]);
                segment.overlaps.forEach(overlap => {
                    if (overlap.interval[0] <= middle && overlap.interval[1] >= middle) neighbours.set(overlap.segment.key, overlap.segment);
                });
                const keys = [...neighbours.keys()].sort();
                // Use the same reference direction for all routes, including return journeys.
                const direction = dot(segment.unit, neighbours.get(keys[0]).unit) >= 0 ? 1 : -1;
                fragments.push({ start: interpolate(segment.start, segment.end, cuts[i - 1]), end: interpolate(segment.start, segment.end, cuts[i]), laneCount: keys.length, lane: (keys.indexOf(segment.key) - (keys.length - 1) / 2) * direction });
            }
            return fragments;
        }));
    }

    /**
     * Sample and smooth in screen space. Dense GTFS vertices must not turn a
     * several-pixel offset into a large loop around every sub-pixel corner.
     */
    function offsetPath(fragments, gap, { maxOffset = gap * 3 } = {}) {
        if (!fragments.length) return [];
        if (fragments.every(fragment => fragment.lane === 0)) {
            return [fragments[0].start, ...fragments.map(fragment => fragment.end)];
        }
        const step = Math.max(gap / 2, 0.001);
        const samples = [];
        let travelled = 0, nextSample = 0;
        fragments.forEach((fragment, index) => {
            const length = Math.hypot(fragment.end.x - fragment.start.x, fragment.end.y - fragment.start.y);
            if (!length) return;
            // Compress the whole bundle rather than clamping different lines onto the same lane.
            const spacing = Math.min(gap, maxOffset * 2 / Math.max(1, (fragment.laneCount || 2) - 1));
            const offset = Math.max(-maxOffset, Math.min(maxOffset, fragment.lane * spacing));
            while (nextSample <= travelled + length) {
                samples.push({ point: interpolate(fragment.start, fragment.end, Math.max(0, (nextSample - travelled) / length)), offset, distance: nextSample });
                nextSample += step;
            }
            travelled += length;
            if (index === fragments.length - 1 && (!samples.length || travelled - samples.at(-1).distance > 0.001)) {
                samples.push({ point: fragment.end, offset, distance: travelled });
            }
        });
        // A triangular window makes lane changes gradual when routes join or leave a road.
        const radius = gap * 4;
        return samples.map((sample, index) => {
            let total = 0, weight = 0;
            for (let j = index; j >= 0 && sample.distance - samples[j].distance < radius; j--) {
                const influence = 1 - (sample.distance - samples[j].distance) / radius;
                total += samples[j].offset * influence; weight += influence;
            }
            for (let j = index + 1; j < samples.length && samples[j].distance - sample.distance < radius; j++) {
                const influence = 1 - (samples[j].distance - sample.distance) / radius;
                total += samples[j].offset * influence; weight += influence;
            }
            const before = samples[Math.max(0, index - 2)].point;
            const after = samples[Math.min(samples.length - 1, index + 2)].point;
            let vector = subtract(after, before);
            let length = Math.hypot(vector.x, vector.y);
            if (length < 0.001) {
                vector = subtract(samples[Math.min(samples.length - 1, index + 1)].point, sample.point);
                length = Math.hypot(vector.x, vector.y);
            }
            const offset = weight ? total / weight : 0;
            return length < 0.001 ? sample.point : {
                x: sample.point.x - vector.y / length * offset,
                y: sample.point.y + vector.x / length * offset
            };
        });
    }

    const api = { layout, offsetPath };
    if (typeof module !== 'undefined' && module.exports) module.exports = api;
    else root.ACTVRouteLanes = api;
})(typeof window !== 'undefined' ? window : globalThis);
