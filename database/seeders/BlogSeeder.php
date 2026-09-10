<?php

namespace Database\Seeders;

use App\Models\Blog;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class BlogSeeder extends Seeder
{
    public function run(): void
    {
        $blogs = [
            [
                'title' => 'Understanding Construction Material Specifications: A Complete Guide',
                'content' => '<p>Construction material specifications are crucial documents that define the quality, performance, and characteristics of materials used in construction projects. These specifications ensure that all materials meet the required standards and comply with building codes.</p>
                
                <h3>Why Specifications Matter</h3>
                <p>Material specifications provide detailed information about:</p>
                <ul>
                    <li>Physical properties and dimensions</li>
                    <li>Performance requirements</li>
                    <li>Installation procedures</li>
                    <li>Quality standards and testing methods</li>
                </ul>
                
                <p>By following proper specifications, contractors and project managers can ensure consistency, quality, and compliance throughout the construction process.</p>
                
                <h3>Key Components</h3>
                <p>Every specification should include:</p>
                <ul>
                    <li>Material type and classification</li>
                    <li>Manufacturer information</li>
                    <li>Technical data sheets</li>
                    <li>Safety data sheets (SDS)</li>
                    <li>Installation guidelines</li>
                </ul>',
                'meta_description' => 'Learn about construction material specifications and how they ensure quality and compliance in building projects.',
                'meta_keywords' => 'construction materials, specifications, building materials, construction standards',
                'status' => 1,
            ],
            [
                'title' => 'Choosing the Right Siding Materials for Your Project',
                'content' => '<p>Selecting the appropriate siding material is one of the most important decisions in construction. The right choice can enhance durability, energy efficiency, and aesthetic appeal.</p>
                
                <h3>Popular Siding Options</h3>
                <p><strong>Aluminum Siding:</strong> Lightweight, durable, and low-maintenance. Ideal for residential and commercial applications.</p>
                <p><strong>Steel Siding:</strong> Extremely durable and fire-resistant. Perfect for industrial and commercial buildings.</p>
                <p><strong>Vinyl Siding:</strong> Cost-effective and versatile. Available in numerous colors and styles.</p>
                <p><strong>Fiber-Cement Siding:</strong> Combines durability with aesthetic appeal. Resistant to fire, moisture, and pests.</p>
                
                <h3>Factors to Consider</h3>
                <ul>
                    <li>Climate and weather conditions</li>
                    <li>Budget constraints</li>
                    <li>Maintenance requirements</li>
                    <li>Local building codes</li>
                    <li>Aesthetic preferences</li>
                </ul>
                
                <p>Consult with a professional to determine the best siding solution for your specific project needs.</p>',
                'meta_description' => 'Discover the best siding materials for your construction project. Compare aluminum, steel, vinyl, and fiber-cement options.',
                'meta_keywords' => 'siding materials, aluminum siding, steel siding, vinyl siding, fiber-cement siding',
                'status' => 1,
            ],
            [
                'title' => 'Roofing Panels: Types and Applications',
                'content' => '<p>Roofing panels are essential components in modern construction, offering durability, efficiency, and aesthetic appeal. Understanding the different types available can help you make informed decisions.</p>
                
                <h3>Types of Roofing Panels</h3>
                <p><strong>Metal Roof Panels:</strong> Long-lasting and energy-efficient. Available in various metals including steel, aluminum, and zinc.</p>
                <p><strong>Insulated Metal Panels:</strong> Provide excellent thermal performance and reduce energy costs.</p>
                <p><strong>Composite Roof Panels:</strong> Combine multiple materials for enhanced performance characteristics.</p>
                
                <h3>Benefits of Modern Roofing Panels</h3>
                <ul>
                    <li>Superior weather resistance</li>
                    <li>Energy efficiency</li>
                    <li>Low maintenance requirements</li>
                    <li>Long service life</li>
                    <li>Environmentally friendly options</li>
                </ul>
                
                <h3>Installation Considerations</h3>
                <p>Proper installation is critical for optimal performance. Always follow manufacturer specifications and local building codes. Consider factors such as:</p>
                <ul>
                    <li>Slope requirements</li>
                    <li>Underlayment needs</li>
                    <li>Flashing details</li>
                    <li>Ventilation requirements</li>
                </ul>',
                'meta_description' => 'Explore different types of roofing panels and their applications in modern construction projects.',
                'meta_keywords' => 'roofing panels, metal roofing, insulated panels, composite roofing',
                'status' => 1,
            ],
            [
                'title' => 'Wall Panels: Modern Solutions for Building Envelopes',
                'content' => '<p>Wall panels have revolutionized construction by providing efficient, durable, and versatile building envelope solutions. From metal to composite materials, there are options for every project type.</p>
                
                <h3>Wall Panel Categories</h3>
                <p><strong>Metal Wall Panels:</strong> Durable and versatile, available in various finishes and profiles.</p>
                <p><strong>Insulated Metal Wall Panels:</strong> Combine structural support with thermal insulation.</p>
                <p><strong>Composite Wall Panels:</strong> Offer enhanced performance through material combination.</p>
                <p><strong>Cementitious Wall Panels:</strong> Fire-resistant and durable option for various applications.</p>
                
                <h3>Advantages</h3>
                <ul>
                    <li>Fast installation</li>
                    <li>Design flexibility</li>
                    <li>Energy efficiency</li>
                    <li>Cost-effectiveness</li>
                    <li>Low maintenance</li>
                </ul>
                
                <p>Wall panels are suitable for commercial, industrial, and residential applications, offering both functional and aesthetic benefits.</p>',
                'meta_description' => 'Learn about modern wall panel solutions for building envelopes, including metal, insulated, and composite options.',
                'meta_keywords' => 'wall panels, metal wall panels, insulated panels, building envelope',
                'status' => 1,
            ],
            [
                'title' => 'Asphalt Shingles: A Comprehensive Overview',
                'content' => '<p>Asphalt shingles remain one of the most popular roofing materials due to their affordability, ease of installation, and wide range of styles and colors.</p>
                
                <h3>Types of Asphalt Shingles</h3>
                <p><strong>3-Tab Shingles:</strong> Traditional, economical option with a flat appearance.</p>
                <p><strong>Architectural Shingles:</strong> Dimensional shingles that provide enhanced visual appeal and durability.</p>
                <p><strong>Premium Shingles:</strong> High-end options with superior performance and aesthetics.</p>
                
                <h3>Key Features</h3>
                <ul>
                    <li>Cost-effective roofing solution</li>
                    <li>Easy installation and repair</li>
                    <li>Wide variety of colors and styles</li>
                    <li>Good wind resistance</li>
                    <li>Fire-resistant options available</li>
                </ul>
                
                <h3>Maintenance Tips</h3>
                <p>Regular maintenance extends the life of asphalt shingle roofs:</p>
                <ul>
                    <li>Annual inspections</li>
                    <li>Prompt repair of damaged shingles</li>
                    <li>Gutter cleaning</li>
                    <li>Moss and algae removal</li>
                </ul>
                
                <p>With proper installation and maintenance, asphalt shingles can provide reliable protection for 20-30 years.</p>',
                'meta_description' => 'Complete guide to asphalt shingles, including types, features, and maintenance tips for residential roofing.',
                'meta_keywords' => 'asphalt shingles, roofing materials, residential roofing, shingle types',
                'status' => 1,
            ],
            [
                'title' => 'Fiber Cement Products: Versatile Building Solutions',
                'content' => '<p>Fiber cement products have gained popularity in construction due to their durability, fire resistance, and versatility. These composite materials offer excellent performance characteristics.</p>
                
                <h3>Common Applications</h3>
                <ul>
                    <li>Siding and cladding</li>
                    <li>Roofing materials</li>
                    <li>Interior panels</li>
                    <li>Accessories and trim</li>
                </ul>
                
                <h3>Benefits</h3>
                <p>Fiber cement products offer numerous advantages:</p>
                <ul>
                    <li>Fire resistance</li>
                    <li>Moisture resistance</li>
                    <li>Durability</li>
                    <li>Low maintenance</li>
                    <li>Design flexibility</li>
                </ul>
                
                <h3>Installation Best Practices</h3>
                <p>Proper installation ensures optimal performance:</p>
                <ul>
                    <li>Follow manufacturer guidelines</li>
                    <li>Use appropriate fasteners</li>
                    <li>Ensure proper spacing</li>
                    <li>Apply correct finishing techniques</li>
                </ul>
                
                <p>Fiber cement products are suitable for both new construction and renovation projects, providing long-lasting and attractive building solutions.</p>',
                'meta_description' => 'Explore fiber cement products and their applications in modern construction, including siding, roofing, and accessories.',
                'meta_keywords' => 'fiber cement, building materials, siding, construction products',
                'status' => 1,
            ],
            [
                'title' => 'MasterFormat: Understanding Construction Division Codes',
                'content' => '<p>MasterFormat is the standard for organizing construction specifications and related documents. Understanding division codes is essential for anyone working in construction, architecture, or project management.</p>
                
                <h3>What is MasterFormat?</h3>
                <p>MasterFormat is a system of numbers and titles for organizing construction information. It provides a standardized way to organize specifications, cost data, and other project information.</p>
                
                <h3>Key Divisions</h3>
                <p><strong>Division 07 - Thermal and Moisture Protection:</strong> Includes roofing, waterproofing, insulation, and related materials.</p>
                <p><strong>Division 08 - Openings:</strong> Doors, windows, and glazing systems.</p>
                <p><strong>Division 09 - Finishes:</strong> Interior finishes including paints, coatings, and flooring.</p>
                <p><strong>Division 10 - Specialties:</strong> Specialized building components and equipment.</p>
                
                <h3>Benefits of Using MasterFormat</h3>
                <ul>
                    <li>Standardized organization across projects</li>
                    <li>Improved communication between team members</li>
                    <li>Easier material sourcing and procurement</li>
                    <li>Better project documentation</li>
                    <li>Industry-wide recognition and acceptance</li>
                </ul>
                
                <p>At Wisselbanken, we organize all our materials according to MasterFormat standards, making it easy to find exactly what you need for your project.</p>',
                'meta_description' => 'Learn about MasterFormat division codes and how they organize construction specifications and materials.',
                'meta_keywords' => 'MasterFormat, division codes, construction specifications, building codes',
                'status' => 1,
            ],
            [
                'title' => 'How to Read and Understand Material Data Sheets',
                'content' => '<p>Material Data Sheets (MDS) and Safety Data Sheets (SDS) are essential documents that provide critical information about construction materials. Understanding how to read these documents is crucial for safe and effective material selection.</p>
                
                <h3>Key Sections in Data Sheets</h3>
                <p><strong>Product Information:</strong> Material name, manufacturer, and product codes.</p>
                <p><strong>Physical Properties:</strong> Dimensions, weight, density, and material composition.</p>
                <p><strong>Performance Characteristics:</strong> Strength ratings, thermal properties, and durability data.</p>
                <p><strong>Installation Requirements:</strong> Temperature ranges, substrate preparation, and application methods.</p>
                <p><strong>Safety Information:</strong> Handling procedures, protective equipment, and hazard warnings.</p>
                
                <h3>What to Look For</h3>
                <ul>
                    <li>Compliance with building codes and standards</li>
                    <li>Compatibility with other materials</li>
                    <li>Environmental conditions and limitations</li>
                    <li>Warranty information</li>
                    <li>Manufacturer contact information</li>
                </ul>
                
                <h3>Using Data Sheets Effectively</h3>
                <p>Always review data sheets before material selection to ensure:</p>
                <ul>
                    <li>Materials meet project specifications</li>
                    <li>Proper installation procedures are followed</li>
                    <li>Safety requirements are understood</li>
                    <li>Performance expectations are realistic</li>
                </ul>
                
                <p>Wisselbanken provides comprehensive data sheets for all products in our catalog, helping you make informed decisions.</p>',
                'meta_description' => 'Learn how to read and understand Material Data Sheets and Safety Data Sheets for construction materials.',
                'meta_keywords' => 'material data sheets, SDS, safety data sheets, construction materials, product specifications',
                'status' => 1,
            ],
            [
                'title' => 'The Importance of Material Submittals in Construction Projects',
                'content' => '<p>Material submittals are a critical part of the construction process, ensuring that all materials meet project specifications before installation. Understanding the submittal process is essential for successful project completion.</p>
                
                <h3>What is a Material Submittal?</h3>
                <p>A material submittal is documentation provided by contractors to architects and engineers showing that proposed materials meet or exceed project requirements. This process ensures quality and compliance throughout the construction process.</p>
                
                <h3>Components of a Complete Submittal</h3>
                <ul>
                    <li>Product data sheets and technical specifications</li>
                    <li>Manufacturer certifications and test reports</li>
                    <li>Sample materials when required</li>
                    <li>Installation instructions and procedures</li>
                    <li>Warranty information</li>
                    <li>Color and finish samples</li>
                </ul>
                
                <h3>Benefits of Proper Submittals</h3>
                <p><strong>Quality Assurance:</strong> Ensures materials meet specified requirements.</p>
                <p><strong>Compliance:</strong> Verifies adherence to building codes and standards.</p>
                <p><strong>Documentation:</strong> Creates a record of approved materials for the project.</p>
                <p><strong>Risk Management:</strong> Reduces the likelihood of material-related issues during construction.</p>
                
                <h3>Common Submittal Mistakes to Avoid</h3>
                <ul>
                    <li>Incomplete documentation</li>
                    <li>Submitting materials that don\'t match specifications</li>
                    <li>Missing required certifications</li>
                    <li>Late submissions causing project delays</li>
                </ul>
                
                <p>Wisselbanken\'s Submittal Builder service helps streamline this process, providing organized documentation for all your material needs.</p>',
                'meta_description' => 'Understand the importance of material submittals in construction projects and how to prepare complete submittal packages.',
                'meta_keywords' => 'material submittals, construction submittals, project documentation, building materials',
                'status' => 1,
            ],
            [
                'title' => 'Selecting the Right Manufacturer for Your Construction Project',
                'content' => '<p>Choosing the right manufacturer is one of the most important decisions in construction. The manufacturer you select can significantly impact project quality, timeline, and budget.</p>
                
                <h3>Factors to Consider</h3>
                <p><strong>Product Quality:</strong> Review manufacturer certifications, test reports, and quality control processes.</p>
                <p><strong>Reputation and Experience:</strong> Consider years in business, project history, and industry recognition.</p>
                <p><strong>Availability and Lead Times:</strong> Ensure materials can be delivered when needed.</p>
                <p><strong>Technical Support:</strong> Access to product experts and installation guidance.</p>
                <p><strong>Warranty and Service:</strong> Comprehensive warranty coverage and responsive customer service.</p>
                
                <h3>Researching Manufacturers</h3>
                <ul>
                    <li>Review product catalogs and technical documentation</li>
                    <li>Check industry certifications and standards compliance</li>
                    <li>Read customer reviews and case studies</li>
                    <li>Request references from similar projects</li>
                    <li>Evaluate manufacturing facilities and processes</li>
                </ul>
                
                <h3>Working with Multiple Manufacturers</h3>
                <p>For large projects, you may work with multiple manufacturers. Consider:</p>
                <ul>
                    <li>Material compatibility between different manufacturers</li>
                    <li>Consistent quality standards across suppliers</li>
                    <li>Coordinated delivery schedules</li>
                    <li>Unified warranty and service support</li>
                </ul>
                
                <p>Wisselbanken partners with leading manufacturers in the industry, providing you access to quality materials from trusted sources.</p>',
                'meta_description' => 'Learn how to select the right manufacturer for your construction project and what factors to consider.',
                'meta_keywords' => 'manufacturer selection, construction materials, building products, supplier evaluation',
                'status' => 1,
            ],
            [
                'title' => 'Understanding Product Variations: Size, Thickness, and Finish Options',
                'content' => '<p>Construction materials come in numerous variations, each suited for specific applications. Understanding size, thickness, and finish options helps you select the right product for your project.</p>
                
                <h3>Size Considerations</h3>
                <p>Material sizes directly impact:</p>
                <ul>
                    <li>Installation efficiency and labor costs</li>
                    <li>Waste minimization</li>
                    <li>Structural requirements</li>
                    <li>Aesthetic appearance</li>
                </ul>
                <p>Always verify available sizes from manufacturers and consider project-specific requirements.</p>
                
                <h3>Thickness Options</h3>
                <p>Material thickness affects:</p>
                <ul>
                    <li>Structural performance and load capacity</li>
                    <li>Thermal and acoustic properties</li>
                    <li>Installation methods</li>
                    <li>Cost implications</li>
                </ul>
                <p>Thicker materials often provide better performance but may increase costs and installation complexity.</p>
                
                <h3>Finish Selection</h3>
                <p>Finishes impact both aesthetics and performance:</p>
                <p><strong>Paint Types:</strong> Different paint formulations offer varying durability, color retention, and environmental resistance.</p>
                <p><strong>Color Options:</strong> Consider color consistency, availability, and long-term appearance.</p>
                <p><strong>Surface Textures:</strong> Smooth, textured, or embossed finishes affect both appearance and performance.</p>
                
                <h3>Making the Right Choice</h3>
                <p>When selecting variations, consider:</p>
                <ul>
                    <li>Project specifications and requirements</li>
                    <li>Environmental conditions</li>
                    <li>Budget constraints</li>
                    <li>Maintenance expectations</li>
                    <li>Design aesthetic goals</li>
                </ul>
                
                <p>Wisselbanken\'s comprehensive product catalog includes detailed information on all available variations, making selection easier.</p>',
                'meta_description' => 'Learn about product variations including sizes, thicknesses, and finishes, and how to select the right options for your project.',
                'meta_keywords' => 'product variations, material sizes, thickness options, finish selection, construction materials',
                'status' => 1,
            ],
            [
                'title' => 'Requesting Quotes: Best Practices for Construction Material Procurement',
                'content' => '<p>Requesting accurate quotes is essential for project budgeting and material procurement. Following best practices ensures you receive competitive pricing and complete information.</p>
                
                <h3>Preparing Your Quote Request</h3>
                <p><strong>Project Information:</strong> Provide clear project details including location, timeline, and scope.</p>
                <p><strong>Material Specifications:</strong> Include complete specifications with division codes, material types, and quantities.</p>
                <p><strong>Variation Details:</strong> Specify sizes, thicknesses, colors, and finishes required.</p>
                <p><strong>Delivery Requirements:</strong> Indicate delivery location, preferred dates, and any special handling needs.</p>
                
                <h3>Information to Request</h3>
                <ul>
                    <li>Unit pricing and total project costs</li>
                    <li>Delivery timelines and availability</li>
                    <li>Minimum order quantities</li>
                    <li>Payment terms and conditions</li>
                    <li>Warranty information</li>
                    <li>Technical support availability</li>
                </ul>
                
                <h3>Comparing Quotes</h3>
                <p>When comparing quotes from multiple suppliers:</p>
                <ul>
                    <li>Ensure all quotes include the same materials and quantities</li>
                    <li>Compare total project costs, not just unit prices</li>
                    <li>Consider delivery timelines and project schedule</li>
                    <li>Evaluate supplier reliability and service quality</li>
                    <li>Review warranty and support offerings</li>
                </ul>
                
                <h3>Common Mistakes to Avoid</h3>
                <ul>
                    <li>Incomplete or unclear specifications</li>
                    <li>Not requesting all necessary information</li>
                    <li>Focusing only on price without considering quality</li>
                    <li>Ignoring delivery timelines</li>
                    <li>Not verifying material availability</li>
                </ul>
                
                <p>Wisselbanken\'s quote request system streamlines this process, helping you get accurate, competitive quotes quickly.</p>',
                'meta_description' => 'Learn best practices for requesting construction material quotes and how to compare supplier proposals effectively.',
                'meta_keywords' => 'material quotes, construction procurement, supplier quotes, material pricing',
                'status' => 1,
            ],
            [
                'title' => 'Building Material Storage and Handling Best Practices',
                'content' => '<p>Proper storage and handling of construction materials is crucial for maintaining quality, preventing damage, and ensuring worker safety. Following best practices protects your investment and project timeline.</p>
                
                <h3>Storage Requirements</h3>
                <p><strong>Environmental Conditions:</strong> Many materials require specific temperature and humidity conditions. Follow manufacturer recommendations.</p>
                <p><strong>Protection from Elements:</strong> Store materials in covered, dry areas protected from rain, snow, and direct sunlight.</p>
                <p><strong>Organization:</strong> Organize materials by type and project phase for easy access and inventory management.</p>
                <p><strong>Accessibility:</strong> Ensure materials are easily accessible while maintaining safety clearances.</p>
                
                <h3>Handling Guidelines</h3>
                <ul>
                    <li>Use appropriate lifting equipment and techniques</li>
                    <li>Protect material edges and surfaces during handling</li>
                    <li>Follow manufacturer handling instructions</li>
                    <li>Use proper personal protective equipment</li>
                    <li>Inspect materials upon delivery and before installation</li>
                </ul>
                
                <h3>Material-Specific Considerations</h3>
                <p><strong>Metal Materials:</strong> Protect from moisture and prevent contact with dissimilar metals to avoid corrosion.</p>
                <p><strong>Composite Materials:</strong> Store flat to prevent warping and protect from UV exposure.</p>
                <p><strong>Insulation Materials:</strong> Keep dry and protected from compression damage.</p>
                <p><strong>Finish Materials:</strong> Store in original packaging until ready for use to prevent damage.</p>
                
                <h3>Safety Considerations</h3>
                <ul>
                    <li>Maintain clear pathways and emergency exits</li>
                    <li>Stack materials safely to prevent collapse</li>
                    <li>Label hazardous materials appropriately</li>
                    <li>Follow OSHA guidelines and local regulations</li>
                    <li>Train workers on proper handling procedures</li>
                </ul>
                
                <p>Proper material management reduces waste, prevents delays, and ensures project quality from start to finish.</p>',
                'meta_description' => 'Learn best practices for storing and handling construction materials to maintain quality and ensure safety.',
                'meta_keywords' => 'material storage, construction materials, material handling, building materials',
                'status' => 1,
            ],
        ];

        foreach ($blogs as $blog) {
            $slug = Str::slug($blog['title']);
            Blog::updateOrCreate(
                ['slug' => $slug],
                [
                    'title' => $blog['title'],
                    'content' => $blog['content'],
                    'featured_image' => null,
                    'meta_description' => $blog['meta_description'],
                    'meta_keywords' => $blog['meta_keywords'],
                    'status' => $blog['status'],
                    'views' => rand(10, 500),
                ]
            );
        }
    }
}
