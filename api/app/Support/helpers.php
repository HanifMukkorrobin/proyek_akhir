<?php

if (!function_exists('paginate_builder')) {
    /**
     * Reusable pagination helper for query builders.
     *
     * @param \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Query\Builder $query
     */
    function paginate_builder($query, int $page = 1, int $perPage = 10): array
    {
        $safePage = max(1, $page);
        $safePerPage = max(1, min(100, $perPage));

        $total = (clone $query)->count();
        $lastPage = max(1, (int) ceil($total / $safePerPage));
        $safePage = min($safePage, $lastPage);

        $items = $query
            ->forPage($safePage, $safePerPage)
            ->get();

        return [
            'data' => $items,
            'halaman_sekarang' => $safePage,
            'per_halaman' => $safePerPage,
            'total_data' => $total,
            'total_halaman' => $lastPage,
        ];
    }
}
