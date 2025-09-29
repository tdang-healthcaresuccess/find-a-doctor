<?php
/**
 * Single Physician Template
 * You can override this by creating find-a-doctor/single-physician.php in your theme
 */

defined('ABSPATH') || exit;

global $fad_current_physician;
$physician = $fad_current_physician;

if (!$physician) {
    return;
}

get_header();
?>

<div class="fad-physician-profile">
    <div class="container">
        <article class="physician-article">
            <header class="physician-header">
                <h1 class="physician-name">
                    <?php echo esc_html($physician['first_name'] . ' ' . $physician['last_name']); ?>
                    <?php if ($physician['degree']): ?>
                        <span class="physician-degree"><?php echo esc_html($physician['degree']); ?></span>
                    <?php endif; ?>
                </h1>
                
                <?php if ($physician['gender']): ?>
                    <p class="physician-gender"><?php echo esc_html($physician['gender']); ?></p>
                <?php endif; ?>
            </header>
            
            <div class="physician-content">
                <?php if ($physician['profile_img_url']): ?>
                    <div class="physician-photo">
                        <img src="<?php echo esc_url($physician['profile_img_url']); ?>" 
                             alt="<?php echo esc_attr($physician['first_name'] . ' ' . $physician['last_name']); ?>"
                             class="physician-image">
                    </div>
                <?php endif; ?>
                
                <div class="physician-details">
                    <?php if (!empty($physician['specialties'])): ?>
                        <div class="physician-specialties">
                            <h3>Specialties</h3>
                            <ul>
                                <?php foreach ($physician['specialties'] as $specialty): ?>
                                    <li><?php echo esc_html($specialty); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>
                    
                    <div class="physician-contact">
                        <h3>Contact Information</h3>
                        
                        <?php if ($physician['phone_number']): ?>
                            <p class="physician-phone">
                                <strong>Phone:</strong> 
                                <a href="tel:<?php echo esc_attr($physician['phone_number']); ?>">
                                    <?php echo esc_html($physician['phone_number']); ?>
                                </a>
                            </p>
                        <?php endif; ?>
                        
                        <?php if ($physician['address'] || $physician['city'] || $physician['state']): ?>
                            <div class="physician-address">
                                <strong>Address:</strong>
                                <address>
                                    <?php if ($physician['practice_name']): ?>
                                        <div class="practice-name"><?php echo esc_html($physician['practice_name']); ?></div>
                                    <?php endif; ?>
                                    
                                    <?php if ($physician['address']): ?>
                                        <div><?php echo esc_html($physician['address']); ?></div>
                                    <?php endif; ?>
                                    
                                    <?php if ($physician['city'] || $physician['state']): ?>
                                        <div>
                                            <?php 
                                            $location_parts = array_filter([
                                                $physician['city'],
                                                $physician['state'] . ' ' . $physician['zip']
                                            ]);
                                            echo esc_html(implode(', ', $location_parts));
                                            ?>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if (fad_has_valid_geolocation($physician['latitude'], $physician['longitude'])): ?>
                                        <div class="coordinates">
                                            <small>📍 <?php echo fad_format_geolocation($physician['latitude'], $physician['longitude']); ?></small>
                                        </div>
                                    <?php endif; ?>
                                </address>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <?php if (!empty($physician['languages'])): ?>
                        <div class="physician-languages">
                            <h3>Languages Spoken</h3>
                            <ul>
                                <?php foreach ($physician['languages'] as $language): ?>
                                    <li><?php echo esc_html($language); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($physician['medical_school']): ?>
                        <div class="physician-education">
                            <h3>Education</h3>
                            <p><strong>Medical School:</strong> <?php echo esc_html($physician['medical_school']); ?></p>
                            
                            <?php if ($physician['residency']): ?>
                                <p><strong>Residency:</strong> <?php echo esc_html($physician['residency']); ?></p>
                            <?php endif; ?>
                            
                            <?php if ($physician['fellowship']): ?>
                                <p><strong>Fellowship:</strong> <?php echo esc_html($physician['fellowship']); ?></p>
                            <?php endif; ?>
                            
                            <?php if ($physician['certification']): ?>
                                <p><strong>Board Certification:</strong> <?php echo esc_html($physician['certification']); ?></p>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($physician['biography']): ?>
                        <div class="physician-biography">
                            <h3>About Dr. <?php echo esc_html($physician['last_name']); ?></h3>
                            <div class="biography-content">
                                <?php echo wp_kses_post(wpautop($physician['biography'])); ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="physician-actions">
                <a href="/physicians/" class="back-to-listing button">← Back to All Doctors</a>
            </div>
        </article>
    </div>
</div>

<style>
.fad-physician-profile {
    padding: 40px 0;
    line-height: 1.6;
}

.fad-physician-profile .container {
    max-width: 1000px;
    margin: 0 auto;
    padding: 0 20px;
}

.physician-header {
    text-align: center;
    margin-bottom: 40px;
    border-bottom: 2px solid #eee;
    padding-bottom: 20px;
}

.physician-name {
    font-size: 2.5em;
    margin: 0 0 10px 0;
    color: #333;
}

.physician-degree {
    color: #666;
    font-weight: normal;
    font-size: 0.8em;
}

.physician-gender {
    color: #666;
    margin: 0;
}

.physician-content {
    display: grid;
    grid-template-columns: 200px 1fr;
    gap: 40px;
    margin-bottom: 40px;
}

@media (max-width: 768px) {
    .physician-content {
        grid-template-columns: 1fr;
        gap: 20px;
    }
}

.physician-photo {
    text-align: center;
}

.physician-image {
    max-width: 100%;
    height: auto;
    border-radius: 12px;
    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
}

.physician-details > div {
    margin-bottom: 30px;
    padding: 20px;
    background: #f9f9f9;
    border-radius: 8px;
}

.physician-details h3 {
    color: #0066cc;
    margin: 0 0 15px 0;
    font-size: 1.2em;
    border-bottom: 1px solid #ddd;
    padding-bottom: 8px;
}

.physician-specialties ul,
.physician-languages ul {
    list-style-type: none;
    padding: 0;
    margin: 0;
}

.physician-specialties li,
.physician-languages li {
    background: #e6f3ff;
    padding: 8px 12px;
    margin: 5px 0;
    border-radius: 4px;
    border-left: 3px solid #0066cc;
}

.physician-phone a {
    color: #0066cc;
    text-decoration: none;
}

.physician-phone a:hover {
    text-decoration: underline;
}

.physician-address address {
    font-style: normal;
    line-height: 1.5;
}

.practice-name {
    font-weight: bold;
    color: #0066cc;
}

.coordinates {
    margin-top: 10px;
    color: #666;
}

.biography-content {
    text-align: justify;
}

.physician-actions {
    text-align: center;
    padding-top: 20px;
    border-top: 1px solid #eee;
}

.back-to-listing {
    display: inline-block;
    padding: 12px 24px;
    background: #0066cc;
    color: white;
    text-decoration: none;
    border-radius: 6px;
    transition: background-color 0.3s;
}

.back-to-listing:hover {
    background: #0052a3;
    color: white;
}
</style>

<?php get_footer(); ?>