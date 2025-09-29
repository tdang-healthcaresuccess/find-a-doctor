<?php
/**
 * Physician Listing Template
 * You can override this by creating find-a-doctor/archive-physicians.php in your theme
 */

defined('ABSPATH') || exit;

global $fad_physicians_list, $fad_pagination;
$physicians = $fad_physicians_list;
$pagination = $fad_pagination;

get_header();
?>

<div class="fad-physicians-listing">
    <div class="container">
        <header class="listing-header">
            <h1>Find a Doctor</h1>
            <p class="listing-description">
                Browse our directory of <?php echo number_format($pagination['total']); ?> physicians. 
                Use the search and filters to find the right doctor for you.
            </p>
        </header>
        
        <!-- Search and Filter Form -->
        <div class="search-filters">
            <form method="get" action="/physicians/" class="search-form">
                <div class="search-row">
                    <input type="text" name="search" placeholder="Search by name, specialty, or location..." 
                           value="<?php echo esc_attr($_GET['search'] ?? ''); ?>" class="search-input">
                    <button type="submit" class="search-button">Search</button>
                </div>
            </form>
        </div>
        
        <?php if (!empty($physicians)): ?>
            <div class="physicians-grid">
                <?php foreach ($physicians as $physician): ?>
                    <div class="physician-card">
                        <div class="physician-card-header">
                            <?php if ($physician['profile_img_url']): ?>
                                <div class="physician-thumbnail">
                                    <img src="<?php echo esc_url($physician['profile_img_url']); ?>" 
                                         alt="<?php echo esc_attr($physician['first_name'] . ' ' . $physician['last_name']); ?>">
                                </div>
                            <?php endif; ?>
                            
                            <div class="physician-info">
                                <h3 class="physician-card-name">
                                    <a href="/physicians/<?php echo esc_attr($physician['slug']); ?>/">
                                        <?php echo esc_html($physician['first_name'] . ' ' . $physician['last_name']); ?>
                                        <?php if ($physician['degree']): ?>
                                            <span class="degree"><?php echo esc_html($physician['degree']); ?></span>
                                        <?php endif; ?>
                                    </a>
                                </h3>
                                
                                <?php if ($physician['gender']): ?>
                                    <p class="physician-gender"><?php echo esc_html($physician['gender']); ?></p>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="physician-card-body">
                            <?php if (!empty($physician['specialties'])): ?>
                                <div class="specialties">
                                    <strong>Specialties:</strong>
                                    <span class="specialty-list">
                                        <?php echo esc_html(implode(', ', array_slice($physician['specialties'], 0, 3))); ?>
                                        <?php if (count($physician['specialties']) > 3): ?>
                                            <span class="more-specialties"> +<?php echo count($physician['specialties']) - 3; ?> more</span>
                                        <?php endif; ?>
                                    </span>
                                </div>
                            <?php endif; ?>
                            
                            <?php if ($physician['city'] && $physician['state']): ?>
                                <div class="location">
                                    <strong>Location:</strong>
                                    <?php echo esc_html($physician['city'] . ', ' . $physician['state']); ?>
                                    <?php if (fad_has_valid_geolocation($physician['latitude'], $physician['longitude'])): ?>
                                        <small class="coordinates">📍</small>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                            
                            <?php if ($physician['phone_number']): ?>
                                <div class="phone">
                                    <strong>Phone:</strong>
                                    <a href="tel:<?php echo esc_attr($physician['phone_number']); ?>">
                                        <?php echo esc_html($physician['phone_number']); ?>
                                    </a>
                                </div>
                            <?php endif; ?>
                            
                            <?php if (!empty($physician['languages'])): ?>
                                <div class="languages">
                                    <strong>Languages:</strong>
                                    <?php echo esc_html(implode(', ', array_slice($physician['languages'], 0, 3))); ?>
                                    <?php if (count($physician['languages']) > 3): ?>
                                        <span class="more-languages"> +<?php echo count($physician['languages']) - 3; ?> more</span>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="physician-card-footer">
                            <a href="/physicians/<?php echo esc_attr($physician['slug']); ?>/" 
                               class="view-profile-btn">View Profile</a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <!-- Pagination -->
            <?php if ($pagination['total_pages'] > 1): ?>
                <nav class="pagination-nav" aria-label="Physicians pagination">
                    <div class="pagination">
                        <?php
                        $current_page = $pagination['current_page'];
                        $total_pages = $pagination['total_pages'];
                        $search = $_GET['search'] ?? '';
                        $search_param = $search ? '&search=' . urlencode($search) : '';
                        ?>
                        
                        <?php if ($current_page > 1): ?>
                            <a href="/physicians/?paged=<?php echo $current_page - 1; ?><?php echo $search_param; ?>" 
                               class="pagination-link prev">‹ Previous</a>
                        <?php endif; ?>
                        
                        <?php
                        // Show page numbers
                        $start = max(1, $current_page - 2);
                        $end = min($total_pages, $current_page + 2);
                        
                        if ($start > 1): ?>
                            <a href="/physicians/?paged=1<?php echo $search_param; ?>" class="pagination-link">1</a>
                            <?php if ($start > 2): ?>
                                <span class="pagination-ellipsis">...</span>
                            <?php endif; ?>
                        <?php endif; ?>
                        
                        <?php for ($i = $start; $i <= $end; $i++): ?>
                            <?php if ($i == $current_page): ?>
                                <span class="pagination-link current"><?php echo $i; ?></span>
                            <?php else: ?>
                                <a href="/physicians/?paged=<?php echo $i; ?><?php echo $search_param; ?>" 
                                   class="pagination-link"><?php echo $i; ?></a>
                            <?php endif; ?>
                        <?php endfor; ?>
                        
                        <?php if ($end < $total_pages): ?>
                            <?php if ($end < $total_pages - 1): ?>
                                <span class="pagination-ellipsis">...</span>
                            <?php endif; ?>
                            <a href="/physicians/?paged=<?php echo $total_pages; ?><?php echo $search_param; ?>" 
                               class="pagination-link"><?php echo $total_pages; ?></a>
                        <?php endif; ?>
                        
                        <?php if ($current_page < $total_pages): ?>
                            <a href="/physicians/?paged=<?php echo $current_page + 1; ?><?php echo $search_param; ?>" 
                               class="pagination-link next">Next ›</a>
                        <?php endif; ?>
                    </div>
                    
                    <div class="pagination-info">
                        Page <?php echo $current_page; ?> of <?php echo $total_pages; ?> 
                        (<?php echo number_format($pagination['total']); ?> doctors total)
                    </div>
                </nav>
            <?php endif; ?>
            
        <?php else: ?>
            <div class="no-results">
                <h2>No doctors found</h2>
                <p>Sorry, we couldn't find any doctors matching your criteria. Please try a different search.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<style>
.fad-physicians-listing {
    padding: 40px 0;
    background: #f8f9fa;
    min-height: 70vh;
}

.fad-physicians-listing .container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 0 20px;
}

.listing-header {
    text-align: center;
    margin-bottom: 40px;
}

.listing-header h1 {
    font-size: 2.5em;
    color: #333;
    margin: 0 0 15px 0;
}

.listing-description {
    font-size: 1.1em;
    color: #666;
    margin: 0;
}

.search-filters {
    background: white;
    padding: 30px;
    border-radius: 12px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    margin-bottom: 40px;
}

.search-form .search-row {
    display: flex;
    gap: 15px;
    max-width: 600px;
    margin: 0 auto;
}

.search-input {
    flex: 1;
    padding: 12px 16px;
    border: 2px solid #ddd;
    border-radius: 8px;
    font-size: 16px;
}

.search-input:focus {
    outline: none;
    border-color: #0066cc;
}

.search-button {
    padding: 12px 24px;
    background: #0066cc;
    color: white;
    border: none;
    border-radius: 8px;
    font-size: 16px;
    cursor: pointer;
    transition: background-color 0.3s;
}

.search-button:hover {
    background: #0052a3;
}

.physicians-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
    gap: 30px;
    margin-bottom: 50px;
}

.physician-card {
    background: white;
    border-radius: 12px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
    overflow: hidden;
    transition: transform 0.3s, box-shadow 0.3s;
}

.physician-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.15);
}

.physician-card-header {
    display: flex;
    padding: 20px;
    border-bottom: 1px solid #eee;
}

.physician-thumbnail {
    width: 60px;
    height: 60px;
    border-radius: 50%;
    overflow: hidden;
    margin-right: 15px;
    flex-shrink: 0;
}

.physician-thumbnail img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.physician-info {
    flex: 1;
}

.physician-card-name {
    margin: 0 0 5px 0;
    font-size: 1.1em;
}

.physician-card-name a {
    color: #333;
    text-decoration: none;
}

.physician-card-name a:hover {
    color: #0066cc;
}

.physician-card-name .degree {
    color: #666;
    font-weight: normal;
    font-size: 0.9em;
}

.physician-gender {
    color: #666;
    margin: 0;
    font-size: 0.9em;
}

.physician-card-body {
    padding: 20px;
}

.physician-card-body > div {
    margin-bottom: 10px;
    font-size: 0.9em;
    line-height: 1.4;
}

.physician-card-body > div:last-child {
    margin-bottom: 0;
}

.specialties, .location, .phone, .languages {
    color: #555;
}

.specialty-list, .more-specialties, .more-languages {
    color: #0066cc;
}

.phone a {
    color: #0066cc;
    text-decoration: none;
}

.phone a:hover {
    text-decoration: underline;
}

.coordinates {
    color: #666;
}

.physician-card-footer {
    padding: 20px;
    background: #f8f9fa;
    text-align: center;
}

.view-profile-btn {
    display: inline-block;
    padding: 10px 20px;
    background: #0066cc;
    color: white;
    text-decoration: none;
    border-radius: 6px;
    font-weight: 500;
    transition: background-color 0.3s;
}

.view-profile-btn:hover {
    background: #0052a3;
    color: white;
}

.pagination-nav {
    text-align: center;
}

.pagination {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 20px;
}

.pagination-link {
    display: inline-block;
    padding: 10px 15px;
    border: 1px solid #ddd;
    color: #333;
    text-decoration: none;
    border-radius: 6px;
    transition: all 0.3s;
}

.pagination-link:hover {
    background: #0066cc;
    color: white;
    border-color: #0066cc;
}

.pagination-link.current {
    background: #0066cc;
    color: white;
    border-color: #0066cc;
}

.pagination-ellipsis {
    padding: 10px 5px;
    color: #666;
}

.pagination-info {
    color: #666;
    font-size: 0.9em;
}

.no-results {
    text-align: center;
    padding: 60px 20px;
    background: white;
    border-radius: 12px;
}

.no-results h2 {
    color: #333;
    margin-bottom: 15px;
}

.no-results p {
    color: #666;
    font-size: 1.1em;
}

@media (max-width: 768px) {
    .physicians-grid {
        grid-template-columns: 1fr;
        gap: 20px;
    }
    
    .search-form .search-row {
        flex-direction: column;
    }
    
    .search-input, .search-button {
        width: 100%;
    }
    
    .pagination {
        flex-wrap: wrap;
        justify-content: center;
    }
}
</style>

<?php get_footer(); ?>