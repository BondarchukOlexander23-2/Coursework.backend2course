<?php

class PaginationComponent extends BaseView
{
    protected function content(): string
    {
        $baseUrl = $this->get('baseUrl', '');
        $currentPage = $this->get('currentPage', 1);
        $totalPages = $this->get('totalPages', 1);
        $params = $this->get('params', []);

        if ($totalPages <= 1) {
            return '';
        }

        $paginationHtml = '<div class="pagination">';

        // Попередня сторінка
        if ($currentPage > 1) {
            $prevParams = array_merge($params, ['page' => $currentPage - 1]);
            $prevUrl = $baseUrl . '?' . http_build_query($prevParams);
            $paginationHtml .= "<a href='{$prevUrl}' class='page-link'>← Попередня</a>";
        }

        // Номери сторінок
        for ($i = max(1, $currentPage - 2); $i <= min($totalPages, $currentPage + 2); $i++) {
            $pageParams = array_merge($params, ['page' => $i]);
            $pageUrl = $baseUrl . '?' . http_build_query($pageParams);
            $activeClass = $i === $currentPage ? ' active' : '';
            $paginationHtml .= "<a href='{$pageUrl}' class='page-link{$activeClass}'>{$i}</a>";
        }

        // Наступна сторінка
        if ($currentPage < $totalPages) {
            $nextParams = array_merge($params, ['page' => $currentPage + 1]);
            $nextUrl = $baseUrl . '?' . http_build_query($nextParams);
            $paginationHtml .= "<a href='{$nextUrl}' class='page-link'>Наступна →</a>";
        }

        $paginationHtml .= '</div>';
        return $paginationHtml;
    }
}