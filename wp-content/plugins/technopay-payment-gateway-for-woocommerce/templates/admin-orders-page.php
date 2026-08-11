<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wrap tpfw-orders-page" dir="rtl">
	<header class="tpfw-orders-page__header">
		<img src="<?php echo esc_url( $view['logo_url'] ); ?>" alt="" class="tpfw-orders-page__logo">
		<div>
			<h1><?php echo esc_html__( 'استرداد سفارشات آنلاین تکنوپی', 'technopay-payment-gateway-for-woocommerce' ); ?></h1>
			<span class="tpfw-orders-page__count">
				<?php echo esc_html__( 'تعداد سفارش‌ها:', 'technopay-payment-gateway-for-woocommerce' ); ?>
				<?php echo esc_html( $view['total'] ); ?>
			</span>
		</div>
	</header>

	<form method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>" class="tpfw-orders-filters">
		<input type="hidden" name="page" value="<?php echo esc_attr( TPFW_Admin_Orders_Page::PAGE_SLUG ); ?>">

		<label class="tpfw-orders-field">
			<span><?php echo esc_html__( 'شماره تماس کاربر', 'technopay-payment-gateway-for-woocommerce' ); ?></span>
			<input type="text" name="customer_mobile" value="<?php echo esc_attr( $view['filters']['customer_mobile'] ); ?>" inputmode="tel" autocomplete="off" placeholder="09121234567">
		</label>

		<label class="tpfw-orders-field">
			<span><?php echo esc_html__( 'مبلغ پرداخت', 'technopay-payment-gateway-for-woocommerce' ); ?></span>
			<input type="text" name="amount" value="<?php echo esc_attr( $view['filters']['amount'] ); ?>" inputmode="decimal" autocomplete="off" placeholder="<?php echo esc_attr__( 'مبلغ دقیق', 'technopay-payment-gateway-for-woocommerce' ); ?>">
		</label>

		<label class="tpfw-orders-field">
			<span><?php echo esc_html__( 'وضعیت', 'technopay-payment-gateway-for-woocommerce' ); ?></span>
			<select name="order_status">
				<option value=""><?php echo esc_html__( 'همه وضعیت‌ها', 'technopay-payment-gateway-for-woocommerce' ); ?></option>
				<?php foreach ( $view['status_options'] as $status_key => $status_label ) : ?>
					<option value="<?php echo esc_attr( $status_key ); ?>" <?php selected( $view['filters']['status'], $status_key ); ?>>
						<?php echo esc_html( $status_label ); ?>
					</option>
				<?php endforeach; ?>
			</select>
		</label>

		<fieldset class="tpfw-orders-field tpfw-orders-field--dates">
			<legend><?php echo esc_html__( 'بازه زمانی ثبت سفارش', 'technopay-payment-gateway-for-woocommerce' ); ?></legend>
			<div>
				<label>
					<span class="screen-reader-text"><?php echo esc_html__( 'از تاریخ', 'technopay-payment-gateway-for-woocommerce' ); ?></span>
					<input type="date" name="date_from" value="<?php echo esc_attr( $view['filters']['date_from'] ); ?>" aria-label="<?php echo esc_attr__( 'از تاریخ', 'technopay-payment-gateway-for-woocommerce' ); ?>">
				</label>
				<span aria-hidden="true">—</span>
				<label>
					<span class="screen-reader-text"><?php echo esc_html__( 'تا تاریخ', 'technopay-payment-gateway-for-woocommerce' ); ?></span>
					<input type="date" name="date_to" value="<?php echo esc_attr( $view['filters']['date_to'] ); ?>" aria-label="<?php echo esc_attr__( 'تا تاریخ', 'technopay-payment-gateway-for-woocommerce' ); ?>">
				</label>
			</div>
		</fieldset>

		<div class="tpfw-orders-filters__actions">
			<button type="submit" class="button tpfw-button tpfw-button--primary">
				<span class="dashicons dashicons-search" aria-hidden="true"></span>
				<?php echo esc_html__( 'مشاهده', 'technopay-payment-gateway-for-woocommerce' ); ?>
			</button>
			<a href="<?php echo esc_url( $view['reset_url'] ); ?>" class="tpfw-orders-reset">
				<span class="dashicons dashicons-trash" aria-hidden="true"></span>
				<?php echo esc_html__( 'حذف همه', 'technopay-payment-gateway-for-woocommerce' ); ?>
			</a>
		</div>
	</form>

	<div class="tpfw-orders-table-wrap">
		<table class="tpfw-orders-table">
			<thead>
				<tr>
					<th scope="col"><?php echo esc_html__( 'ردیف', 'technopay-payment-gateway-for-woocommerce' ); ?></th>
					<th scope="col"><?php echo esc_html__( 'نام و نام خانوادگی کاربر', 'technopay-payment-gateway-for-woocommerce' ); ?></th>
					<th scope="col"><?php echo esc_html__( 'شماره تماس کاربر', 'technopay-payment-gateway-for-woocommerce' ); ?></th>
					<th scope="col"><?php echo esc_html__( 'شناسه پرداخت', 'technopay-payment-gateway-for-woocommerce' ); ?></th>
					<th scope="col"><?php echo esc_html__( 'تاریخ ثبت پرداخت', 'technopay-payment-gateway-for-woocommerce' ); ?></th>
					<th scope="col"><?php echo esc_html__( 'مبلغ پرداخت', 'technopay-payment-gateway-for-woocommerce' ); ?></th>
					<th scope="col"><?php echo esc_html__( 'مبلغ استرداد', 'technopay-payment-gateway-for-woocommerce' ); ?></th>
					<th scope="col"><?php echo esc_html__( 'وضعیت', 'technopay-payment-gateway-for-woocommerce' ); ?></th>
					<th scope="col"><?php echo esc_html__( 'اقدامات', 'technopay-payment-gateway-for-woocommerce' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php if ( empty( $view['rows'] ) ) : ?>
					<tr>
						<td colspan="9" class="tpfw-orders-table__empty">
							<span class="dashicons dashicons-search" aria-hidden="true"></span>
							<?php echo esc_html__( 'سفارش تکنوپی مطابق با این فیلترها پیدا نشد.', 'technopay-payment-gateway-for-woocommerce' ); ?>
						</td>
					</tr>
				<?php else : ?>
					<?php foreach ( $view['rows'] as $row ) : ?>
						<tr>
							<td data-label="<?php echo esc_attr__( 'ردیف', 'technopay-payment-gateway-for-woocommerce' ); ?>"><?php echo esc_html( $row['number'] ); ?></td>
							<td class="tpfw-orders-table__name" data-label="<?php echo esc_attr__( 'نام و نام خانوادگی کاربر', 'technopay-payment-gateway-for-woocommerce' ); ?>"><?php echo esc_html( $row['customer_name'] ); ?></td>
							<td data-label="<?php echo esc_attr__( 'شماره تماس کاربر', 'technopay-payment-gateway-for-woocommerce' ); ?>">
								<div class="tpfw-copy-value">
									<span dir="ltr"><?php echo esc_html( $row['customer_mobile'] ); ?></span>
									<?php if ( $row['customer_mobile_raw'] !== '' ) : ?>
									<button type="button" class="tpfw-copy-button" data-copy="<?php echo esc_attr( $row['customer_mobile_raw'] ); ?>" aria-label="<?php echo esc_attr__( 'کپی شماره تماس', 'technopay-payment-gateway-for-woocommerce' ); ?>" title="<?php echo esc_attr__( 'کپی', 'technopay-payment-gateway-for-woocommerce' ); ?>">
											<span class="dashicons dashicons-admin-page" aria-hidden="true"></span>
										</button>
									<?php endif; ?>
								</div>
							</td>
							<td data-label="<?php echo esc_attr__( 'شناسه پرداخت', 'technopay-payment-gateway-for-woocommerce' ); ?>">
								<div class="tpfw-copy-value">
									<span dir="ltr"><?php echo $row['track_number'] !== '' ? esc_html( $row['track_number'] ) : '—'; ?></span>
									<?php if ( $row['track_number_raw'] !== '' ) : ?>
									<button type="button" class="tpfw-copy-button" data-copy="<?php echo esc_attr( $row['track_number_raw'] ); ?>" aria-label="<?php echo esc_attr__( 'کپی شناسه پرداخت', 'technopay-payment-gateway-for-woocommerce' ); ?>" title="<?php echo esc_attr__( 'کپی', 'technopay-payment-gateway-for-woocommerce' ); ?>">
											<span class="dashicons dashicons-admin-page" aria-hidden="true"></span>
										</button>
									<?php endif; ?>
								</div>
							</td>
							<td data-label="<?php echo esc_attr__( 'تاریخ ثبت پرداخت', 'technopay-payment-gateway-for-woocommerce' ); ?>"><?php echo esc_html( $row['paid_at'] ); ?></td>
							<td class="tpfw-orders-table__money" data-label="<?php echo esc_attr__( 'مبلغ پرداخت', 'technopay-payment-gateway-for-woocommerce' ); ?>"><?php echo esc_html( $row['amount'] ); ?></td>
							<td class="tpfw-orders-table__money" data-label="<?php echo esc_attr__( 'مبلغ استرداد', 'technopay-payment-gateway-for-woocommerce' ); ?>"><?php echo esc_html( $row['refund_amount'] ); ?></td>
							<td data-label="<?php echo esc_attr__( 'وضعیت', 'technopay-payment-gateway-for-woocommerce' ); ?>">
								<span class="tpfw-status tpfw-status--<?php echo esc_attr( $row['status_tone'] ); ?>">
									<?php echo esc_html( $row['status_label'] ); ?>
								</span>
							</td>
							<td data-label="<?php echo esc_attr__( 'اقدامات', 'technopay-payment-gateway-for-woocommerce' ); ?>">
								<?php if ( $row['can_refund'] ) : ?>
									<a href="<?php echo esc_url( $row['order_url'] . '#woocommerce-order-items' ); ?>" class="tpfw-order-action tpfw-order-action--refund">
										<span class="dashicons dashicons-undo" aria-hidden="true"></span>
										<?php echo esc_html__( 'استرداد پرداخت', 'technopay-payment-gateway-for-woocommerce' ); ?>
									</a>
								<?php elseif ( $row['has_refund'] ) : ?>
									<a href="<?php echo esc_url( $row['order_url'] ); ?>" class="tpfw-order-action tpfw-order-action--details" aria-label="<?php echo esc_attr__( 'مشاهده سفارش', 'technopay-payment-gateway-for-woocommerce' ); ?>" title="<?php echo esc_attr__( 'مشاهده سفارش', 'technopay-payment-gateway-for-woocommerce' ); ?>">
										<span class="dashicons dashicons-info-outline" aria-hidden="true"></span>
									</a>
								<?php else : ?>
									<span aria-hidden="true">—</span>
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
				<?php endif; ?>
			</tbody>
		</table>
	</div>

	<?php if ( $view['pagination'] !== '' ) : ?>
		<nav class="tpfw-orders-pagination" aria-label="<?php echo esc_attr__( 'صفحه‌بندی سفارش‌ها', 'technopay-payment-gateway-for-woocommerce' ); ?>">
			<?php echo wp_kses_post( $view['pagination'] ); ?>
		</nav>
	<?php endif; ?>
</div>
